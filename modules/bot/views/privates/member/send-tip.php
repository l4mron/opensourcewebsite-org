<b><?= Yii::t('bot', 'Group') ?>: <?= $chatTip->chat->title ?></b><?= $chatTip->chat->username ? ' (@' . $chatTip->chat->username . ')' : '' ?><br/>
<br/>
<?= Yii::t('bot', 'Preparation') ?>. <?= Yii::t('bot', 'Financial thanks for this member') ?>.<br/>
<br/>
<?= Yii::t('bot', 'Receiver') ?>: <?= $chatTip->toUser->getFullLink() ?><br/>
