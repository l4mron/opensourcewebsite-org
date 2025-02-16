<?php

namespace app\modules\bot\controllers\privates;

use app\models\WalletTransaction;
use app\modules\bot\components\Controller;
use app\modules\bot\components\helpers\Emoji;
use app\modules\bot\components\helpers\ExternalLink;
use app\modules\bot\components\helpers\MessageWithEntitiesConverter;
use app\modules\bot\components\helpers\PaginationButtons;
use app\modules\bot\models\Chat;
use app\modules\bot\models\ChatMember;
use app\modules\bot\models\ChatSetting;
use app\modules\bot\models\ChatTip;
use Yii;
use yii\data\Pagination;

/**
 * Class GroupGuestController
 *
 * @package app\modules\bot\controllers\privates
 */
class GroupGuestController extends Controller
{
    /**
     * @param int $id Chat->id
     * @return array
     */
    public function actionView($id = null)
    {
        $chat = Chat::findOne($id);

        if (!isset($chat) || !$chat->isGroup()) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $chatMember = $chat->getChatMemberByUserId();

        if (!$chat->hasUsername() && !$chatMember) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->clearInputRoute();

        $buttons = [];

        if ($chatMember) {

            $toUserChatMember = ChatMember::findOne([
                'chat_id' => $chatMember->getChatId(),
                'status' => ChatMember::STATUS_CREATOR,
            ]);

            if (isset($toUserChatMember) && $toUserChatMember->id != $chatMember->id) {
                $buttons[] = [
                    [
                        'callback_data' => self::createRoute('renew-membership', [
                            'id' => $chatMember->id,
                        ]),
                        'text' => Yii::t('bot', 'Renew membership'),
                        'visible' => $chatMember->hasMembershipTariff(),
                    ],
                ];
            }

            $buttons[] = [
                [
                    'callback_data' => self::createRoute('remove-membership', [
                        'id' => $chatMember->id,
                    ]),
                    'text' => Yii::t('bot', 'Remove membership'),
                    'visible' => $chatMember->hasExpiredMembership()
                ],
            ];

            $buttons[] = [
                [
                    'callback_data' => self::createRoute('input-intro-text', [
                        'id' => $chatMember->id,
                    ]),
                    'text' => Yii::t('bot', 'My public intro'),
                ],
            ];

            $buttons[] = [
                [
                    'callback_data' => MemberReviewController::createRoute('index', [
                        'id' => $chatMember->id,
                    ]),
                    'text' => Yii::t('bot', 'Reviews') . ($chatMember->getPositiveReviewsCount() ? ' ' . Emoji::LIKE . ' ' . $chatMember->getPositiveReviewsCount() : '') . ($chatMember->getNegativeReviewsCount() ? ' ' . Emoji::DISLIKE . ' ' . $chatMember->getNegativeReviewsCount() : ''),
                    'visible' => $chatMember->getActiveReviews()->exists(),
                ],
            ];
        }

        if ($chat->isFaqOn()) {
            $buttons[] = [
                [
                    'callback_data' => GroupGuestFaqController::createRoute('word-list', [
                        'chatId' => $chat->id,
                    ]),
                    'text' => Yii::t('bot', 'Help Center'),
                ],
            ];
        }

        $buttons[] = [
            [
                'callback_data' => self::createRoute('premium-members', [
                    'id' => $chat->id,
                ]),
                'text' => Yii::t('bot', 'Premium members'),
                'visible' => $chat->isMembershipOn(),
            ],
        ];

        $buttons[] = [
            [
                'callback_data' => self::createRoute('members-with-intro', [
                    'id' => $chat->id,
                ]),
                'text' => Yii::t('bot', 'Members with intro'),
                'visible' => (bool)$chat->getUsername(),
            ],
        ];

        $buttons[] = [
            [
                'callback_data' => self::createRoute('members-with-reviews', [
                    'id' => $chat->id,
                ]),
                'text' => Yii::t('bot', 'Members with reviews'),
                'visible' => (bool)$chat->getUsername(),
            ],
        ];

        $buttons[] = [
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ],
            [
                'callback_data' => GroupController::createRoute('view', [
                    'chatId' => $chat->id,
                ]),
                'text' => Emoji::MANAGE,
                'visible' => $chatMember && $chatMember->isActiveAdministrator(),
            ],
            [
                'url' => ExternalLink::getTelegramAccountLink($chat->getUsername()),
                'text' => Yii::t('bot', 'Group'),
                'visible' => (bool)$chat->getUsername(),
            ],
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('view', [
                    'chat' => $chat,
                    'user' => $this->getTelegramUser(),
                    'chatMember' => $chatMember,
                ]),
                $buttons,
                [
                    'disablePreview' => true,
                ]
            )
            ->build();
    }

    /**
     * @param int $id ChatMember->id
     * @return array
     */
    public function actionInputIntroText($id = null)
    {
        $chatMember = ChatMember::findOne($id);

        if (!isset($chatMember)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('input-intro-text', [
                'id' => $chatMember->id,
            ]));

        if ($this->getUpdate()->getMessage()) {
            if ($text = MessageWithEntitiesConverter::toHtml($this->getUpdate()->getMessage())) {
                $chatMember->intro = $text;

                if ($chatMember->validate('intro')) {
                    $chatMember->save(false);

                    return $this->runAction('view', [
                         'id' => $chatMember->getChatId(),
                     ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('input-intro-text', [
                    'chatMember' => $chatMember,
                ]),
                [
                    [
                        [
                            'callback_data' => self::createRoute('view', [
                                'id' => $chatMember->getChatId(),
                            ]),
                            'text' => Emoji::BACK,
                        ],
                        [
                            'callback_data' => self::createRoute('delete-intro', [
                                'id' => $chatMember->id,
                            ]),
                            'text' => Emoji::DELETE,
                            'visible' => (bool)$chatMember->intro,
                        ],
                    ],
                ],
                [
                    'disablePreview' => true,
                ]
            )
            ->build();
    }

    /**
     * @param int $id ChatMember->id
     * @return array
     */
    public function actionDeleteIntro($id = null)
    {
        $chatMember = ChatMember::findOne($id);

        if (!isset($chatMember)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $chatMember->intro = null;
        $chatMember->save(false);

        return $this->runAction('view', [
             'id' => $chatMember->getChatId(),
         ]);
    }

    /**
     * @param int $id Chat->id
     * @param int $page
     * @return array
     */
    public function actionPremiumMembers($id = null, $page = 1)
    {
        $chat = Chat::findOne($id);

        if (!isset($chat) || !$chat->isGroup()) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $chatMember = $chat->getChatMemberByUserId();

        if (!$chat->hasUsername() && !$chatMember) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->clearInputRoute();

        $query = $chat->getPremiumChatMembers();

        $pagination = new Pagination([
            'totalCount' => $query->count(),
            'pageSize' => 9,
            'params' => [
                'page' => $page,
            ],
            'pageSizeParam' => false,
            'validatePage' => true,
        ]);

        $paginationButtons = PaginationButtons::build($pagination, function ($page) use ($chat) {
            return self::createRoute('premium-members', [
                'id' => $chat->id,
                'page' => $page,
            ]);
        });

        $buttons = [];

        $members = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        if ($members) {
            foreach ($members as $member) {
                $memberUser = $member->user;
                $contact = $memberUser->globalUser->contact;

                $buttons[][] = [
                    'callback_data' => MemberController::createRoute('id', [
                        'id' => $member->id,
                    ]),
                    'text' => ($contact ? $contact->getTelegramDisplayName() : $memberUser->getDisplayName()),
                ];
            }

            if ($paginationButtons) {
                $buttons[] = $paginationButtons;
            }
        }

        $buttons[] = [
            [
                'callback_data' => self::createRoute('view', [
                    'id' => $chat->id,
                ]),
                'text' => Emoji::BACK,
            ],
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ],
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('premium-members', [
                    'chat' => $chat,
                ]),
                $buttons
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $page
     * @return array
     */
    public function actionMembersWithIntro($id = null, $page = 1)
    {
        $chat = Chat::findOne($id);

        if (!isset($chat) || !$chat->isGroup()) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $chatMember = $chat->getChatMemberByUserId();

        if (!$chat->hasUsername() && !$chatMember) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->clearInputRoute();

        $query = $chat->getChatMembersWithIntro();

        $pagination = new Pagination([
            'totalCount' => $query->count(),
            'pageSize' => 9,
            'params' => [
                'page' => $page,
            ],
            'pageSizeParam' => false,
            'validatePage' => true,
        ]);

        $paginationButtons = PaginationButtons::build($pagination, function ($page) use ($chat) {
            return self::createRoute('members-with-intro', [
                'id' => $chat->id,
                'page' => $page,
            ]);
        });

        $buttons = [];

        $members = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        if ($members) {
            foreach ($members as $member) {
                $memberUser = $member->user;
                $contact = $memberUser->globalUser->contact;

                $buttons[][] = [
                    'callback_data' => MemberController::createRoute('id', [
                        'id' => $member->id,
                    ]),
                    'text' => ($contact ? $contact->getTelegramDisplayName() : $memberUser->getDisplayName()),
                ];
            }

            if ($paginationButtons) {
                $buttons[] = $paginationButtons;
            }
        }

        $buttons[] = [
            [
                'callback_data' => self::createRoute('view', [
                    'id' => $chat->id,
                ]),
                'text' => Emoji::BACK,
            ],
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ],
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('members-with-intro', [
                    'chat' => $chat,
                ]),
                $buttons
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $page
     * @return array
     */
    public function actionMembersWithReviews($id = null, $page = 1)
    {
        $chat = Chat::findOne($id);

        if (!isset($chat) || !$chat->isGroup()) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $chatMember = $chat->getChatMemberByUserId();

        if (!$chat->hasUsername() && !$chatMember) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->clearInputRoute();

        $query = $chat->getChatMembersWithPositiveReviews();

        $pagination = new Pagination([
            'totalCount' => $query->count(),
            'pageSize' => 9,
            'params' => [
                'page' => $page,
            ],
            'pageSizeParam' => false,
            'validatePage' => true,
        ]);

        $paginationButtons = PaginationButtons::build($pagination, function ($page) use ($chat) {
            return self::createRoute('members-with-reviews', [
                'id' => $chat->id,
                'page' => $page,
            ]);
        });

        $buttons = [];

        $members = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        if ($members) {
            foreach ($members as $member) {
                $memberUser = $member->user;
                $contact = $memberUser->globalUser->contact;

                $buttons[][] = [
                    'callback_data' => MemberController::createRoute('id', [
                        'id' => $member->id,
                    ]),
                    'text' => ($member->getPositiveReviewsCount() ? ' ' . Emoji::LIKE . ' ' . $member->getPositiveReviewsCount() . ' - ' : '') . ($contact ? $contact->getTelegramDisplayName() : $memberUser->getDisplayName()),
                ];
            }

            if ($paginationButtons) {
                $buttons[] = $paginationButtons;
            }
        }

        $buttons[] = [
            [
                'callback_data' => self::createRoute('view', [
                    'id' => $chat->id,
                ]),
                'text' => Emoji::BACK,
            ],
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ],
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('members-with-reviews', [
                    'chat' => $chat,
                ]),
                $buttons
            )
            ->build();
    }

    /**
     * @param int $id ChatMember->id
     *
     * @return array
     */
    public function actionRemoveMembership($id = null)
    {
        $chatMember = ChatMember::findOne($id);

        if (!isset($chatMember) || ($chatMember->getUserId() != $this->getTelegramUser()->getId())) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }
        // remove membership
        $chatMember->membership_date = null;
        $chatMember->membership_tariff_price = null;
        $chatMember->membership_tariff_days = null;
        // remove slow mode
        $chatMember->slow_mode_messages_limit = null;
        $chatMember->slow_mode_messages_skip_days = null;
        $chatMember->slow_mode_messages_skip_hours = null;

        $chatMember->save();

        return $this->actionView($chatMember->getChatId());
    }

    /**
     * @param int $id Chat->id
     * @param int $page
     * @return array
     */
    public function actionRenewMembership($id = null)
    {
        $chatMember = ChatMember::findOne($id);

        if (!isset($chatMember) || !$chatMember->chat->isGroup() || !$chatMember->hasMembershipTariff()) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $toUserChatMember = ChatMember::findOne([
            'chat_id' => $chatMember->getChatId(),
            'status' => ChatMember::STATUS_CREATOR,
        ]);

        if (!isset($toUserChatMember) || $toUserChatMember->id == $chatMember->id) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $wallet = $chatMember->user->getWalletByCurrencyId($chatMember->chat->currency->id);

        if (!isset($wallet) || !$wallet->hasAmount($chatMember->membership_tariff_price)) {
            return $this->getResponseBuilder()
            ->answerCallbackQuery(
                $this->render('../alert', [
                    'alert' => Yii::t('bot', 'You do not have wallets with sufficient balance') . '.',
                ]),
                true
            )
            ->build();
        }

        $walletTransaction = new WalletTransaction([
            'from_user_id' => $chatMember->user->globalUser->id,
            'to_user_id' => $toUserChatMember->user->globalUser->id,
            'amount' => $chatMember->membership_tariff_price,
            'currency_id' => $chatMember->chat->currency->id,
            'type' => WalletTransaction::GROUP_MEMBERSHIP_TYPE,
            'anonymity' => 1,
        ]);

        $walletTransaction->setData(WalletTransaction::CHAT_MEMBER_ID_DATA_KEY, $chatMember->id);

        $this->getState()->setItem($walletTransaction);

        $this->getState()->setBackRoute(self::createRoute('renew-membership', [
            'id' => $chatMember->id,
        ]));

        $buttons[] = [
            [
                'callback_data' => TransactionController::createRoute('confirmation'),
                'text' => Yii::t('bot', 'CONTINUE'),
            ],
        ];

        $buttons[] = [
            [
                'callback_data' => self::createRoute('view', [
                    'id' => $chatMember->getChatId(),
                ]),
                'text' => Emoji::BACK,
            ],
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ],
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('renew-membership', [
                    'chatMember' => $chatMember,
                ]),
                $buttons
            )
            ->build();
    }

    /**
     * @param int $id ChatMember->id
     *
     * @return array
     */
    public function actionSendTip($id = null, $chatTipId = null)
    {
        $chatMember = ChatMember::findOne($id);

        if (!isset($chatMember)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $chatTip = ChatTip::findOne($chatTipId);

        if (!isset($chatTip)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $walletTransaction = new WalletTransaction([
            'from_user_id' => $this->getTelegramUser()->getUserId(),
            'to_user_id' => $chatMember->user->globalUser->id,
            'type' => WalletTransaction::GROUP_ANONYMOUS_REPLY_TIP_TYPE,
            'anonymity' => 1,
        ]);

        $walletTransaction->setData(WalletTransaction::CHAT_TIP_ID_DATA_KEY, $chatTip->id);

        $this->getState()->setItem($walletTransaction);

        $this->getState()->setBackRoute(self::createRoute('send-tip', [
            'id' => $id,
            'chatTipId' => $chatTipId,
        ]));

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('send-tip', [
                    'chatTip' => $chatTip,
                ]),
                [
                    [
                        [
                            'callback_data' => TransactionController::createRoute('index', [
                                'type' => WalletTransaction::GROUP_ANONYMOUS_REPLY_TIP_TYPE,
                            ]),
                            'text' => Yii::t('bot', 'CONTINUE'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => MenuController::createRoute(),
                            'text' => Emoji::MENU,
                        ],
                    ],
                ]
            )
            ->build();
    }
}
