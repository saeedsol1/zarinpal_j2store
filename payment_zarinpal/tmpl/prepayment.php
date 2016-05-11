<?php defined('_JEXEC') or die('Restricted access'); ?>
<div class="note">
    <?php echo $vars->display_name; ?>
    <br />
    <br/>
    <?php echo nl2br($vars->onbeforepayment_text); ?>
</div>
<br/>
<div class="note">
    <?php if(!isset($vars->error)){ ?>
        <form action="<?php echo $vars->redirectToZP ?>" method="get">
            <input type="submit" class="btn btn-primary button" value="<?php  echo JText::_("J2STORE_ZARINPAL_PLACE_ORDER"); ?>"/>
        </form>
    <?php } else { ?>
        <p>
            <?php echo $vars->error; ?>
        </p>
    <?php } ?>
</div>