<?php

use app\models\WalletTransaction;

?>
<?php if ($walletTransaction->amount) : ?>
<b><?= Yii::t('bot', 'Transfer confirmation') ?></b><br/>
<br/>
<?= Yii::t('bot', 'Receiver') ?>: <?= $walletTransaction->toUser->botUser->getFullLink() ?><br/>
<?= Yii::t('bot', 'Total amount') ?>: <?= $walletTransaction->getAmountPlusFee() ?> <?= $walletTransaction->currency->code ?><br/>
  • <?= Yii::t('bot', 'Amount') ?>: <?= $walletTransaction->amount ?> <?= $walletTransaction->currency->code ?><br/>
  • <?= Yii::t('bot', 'Fee') ?>: <?= $walletTransaction->fee ?> <?= $walletTransaction->currency->code ?><br/>
<?php else : ?>
<b><?= Yii::t('bot', 'Send amount for transfer') ?>:</b><br/>
<br/>
<i><?= Yii::t('bot', 'Available amount'); ?>: <?= $walletTransaction->fromUser->getWalletByCurrencyId(@$walletTransaction->currency->id)->getAmountMinusFee() . ' ' . @$walletTransaction->currency->code; ?></i><br/>
————<br/>
<?= Yii::t('bot', 'Receiver') ?>: <?= $walletTransaction->toUser->botUser->getFullLink() ?><br/>
<?php endif; ?>
