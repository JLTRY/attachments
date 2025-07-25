<?php

/**
 * Attachments component
 *
 * @package Attachments
 * @subpackage Attachments_Component
 *
 * @copyright Copyright (C) 2007-2025 Jonathan M. Cameron, All Rights Reserved
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link https://github.com/jmcameron/attachments
 * @author Jonathan M. Cameron
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


// Load the tooltip behavior.
HTMLHelper::_('bootstrap.tooltip');

// Add the plugins stylesheet to style the list of attachments
/** @var \Joomla\CMS\Application\CMSApplication $app */
$app = Factory::getApplication();
$user = $app->getIdentity();
$document = $app->getDocument();
$uri = Uri::getInstance();

// Get the component parameters
$params = ComponentHelper::getParams('com_attachments');
$secure = $params->get('secure', false);

$attachment = $this->attachment;

if ($this->change_parent) {
    $parent_id = $this->selpar_parent_id;
} else {
    $parent_id = $attachment->parent_id;
}

// Set up the create/modify dates
$tz = new DateTimeZone($user->getParam('timezone', $app->getCfg('offset')));

$cdate = Factory::getDate($attachment->created);
$cdate->setTimezone($tz);
$created = $cdate->format("Y-m-d H:i", true);

$mdate = Factory::getDate($attachment->modified);
$mdate->setTimezone($tz);
$modified = $mdate->format("Y-m-d H:i", true);

$update = $this->update;

$change_entity_tooltip = Text::sprintf('ATTACH_CHANGE_ENTITY_S_TOOLTIP', $attachment->parent_entity_name) . '::' .
    Text::_('ATTACH_CHANGE_ENTITY_TOOLTIP2');

if ($update == 'file') {
    $enctype = "enctype=\"multipart/form-data\"";
} else {
    $enctype = '';
}

?>
<?php if ($this->in_popup) : ?>
<div class="attachmentsBackendTitle">
    <h1><?php echo Text::_('ATTACH_UPDATE_ATTACHMENT_COLON') . " " . $attachment->filename; ?></h1>
</div>
<?php endif; ?>
<form class="attachmentsBackend" action="<?php echo $this->save_url; ?>" method="post" <?php echo $enctype ?>
      name="adminForm" id="adminForm">
<fieldset class="adminform">
<table class="admintable">
<tbody>
  <tr>
<?php if ($this->change_parent) : ?>
      <td class="key"><label for="parent_id"><b><?php
          echo $this->selpar_label ?></b></label></td>
      <td colspan="5"><input id="parent_title" value="<?php echo $this->selpar_parent_title; ?>"
                 disabled="disabled" type="text" size="60" />&nbsp;
    <?php
    $modalId = 'attachment';
    $modalParams['title']  = $this->escape($this->selpar_btn_tooltip);
    $modalParams['url']    = $this->selpar_btn_url;
    $modalParams['height'] = '100%';
    $modalParams['width']  = '100%';
    $modalParams['bodyHeight'] = 70;
    $modalParams['modalWidth'] = 80;
    echo HTMLHelper::_('bootstrap.renderModal', 'modal-' . $modalId, $modalParams);

    echo '<button type="button" class="btn btn-link" data-bs-toggle="modal" data-bs-target="#modal-' . $modalId . '">'
        . $this->selpar_btn_text .
    '</button>';

    ?>
      </td>
<?php else : ?>
      <td class="key"><label><?php echo
        Text::sprintf('ATTACH_ATTACHED_TO', $attachment->parent_entity_name); ?></label></td>
       <td class="at_title" colspan="3"><?php
        if ($attachment->parent_id == null) {
            echo '<span class="error">' . $attachment->parent_title . '</span>';
        } else {
            echo $attachment->parent_title;
        } ?>
        <div class="right">
        <a class="changeButton hasTip" href="<?php echo $this->change_parent_url; ?>"
           title="<?php echo $change_entity_tooltip; ?>"
           ><?php echo Text::sprintf('ATTACH_CHANGE_ENTITY_S', $attachment->parent_entity_name) ?></a></div>
       </td>
       <td class="switch" colspan="2"> <?php echo Text::_('ATTACH_SWITCH_TO_COLON') ?>
    <?php
    // Create all the buttons to switch to other types of parents
    foreach ($this->entity_info as $einfo) {
        $parent_type = $einfo['parent_type'];
        $centity = $einfo['id'];
        $cename = $einfo['name'];
        if (($parent_type != $attachment->parent_type) || ($centity != $attachment->parent_entity)) {
            $url = $this->change_parent_url . "&amp;new_parent_type=" . $parent_type;
            $tooltip = Text::sprintf('ATTACH_SWITCH_ATTACHMENT_TO_S_TOOLTIP', $cename) . '::' .
                Text::_('ATTACH_SWITCH_ATTACHMENT_TO_TOOLTIP2');
            if ($centity != 'default') {
                $url .= '.' . $centity;
            }
            if ($update == 'file') {
                $url .= '&amp;update=file';
            }
            if ($update == 'url') {
                $url .= '&amp;update=url';
            }
            echo "<a class=\"changeButton hasTip\" href=\"$url\" title=\"$tooltip\">$cename</a>";
        }
    }
    ?>
      </td>

<?php endif; ?>
  <tr><td class="key"><label><?php echo Text::_('ATTACH_ATTACHMENT_TYPE'); ?></label></td>
  <td colspan="5"><?php echo Text::_('ATTACH_' . StringHelper::strtoupper($attachment->uri_type));?>
  <?php if (($attachment->uri_type == 'file') && ( $update != 'url' )) : ?>
      <a class="changeButton hasTip" href="<?php echo $this->change_url_url ?>"
         title="<?php echo Text::_('ATTACH_CHANGE_TO_URL') . '::' . Text::_('ATTACH_CHANGE_TO_URL_TOOLTIP'); ?>"
         ><?php echo Text::_('ATTACH_CHANGE_TO_URL') ?></a>
  <?php elseif (($attachment->uri_type == 'url') && ($update != 'file')) : ?>
      <a class="changeButton hasTip" href="<?php echo $this->change_file_url ?>"
         title="<?php echo Text::_('ATTACH_CHANGE_TO_FILE') . '::' . Text::_('ATTACH_CHANGE_TO_FILE_TOOLTIP'); ?>"
         ><?php echo Text::_('ATTACH_CHANGE_TO_FILE') ?></a>
  <?php elseif (
    (($attachment->uri_type == 'file') && ($update != 'file')) ||
                 (($attachment->uri_type == 'url') && ($update != 'url'))
) : ?>
      <a class="changeButton hasTip" href="<?php echo $this->normal_update_url ?>"
         title="<?php echo Text::_('ATTACH_NORMAL_UPDATE') . '::' . Text::_('ATTACH_NORMAL_UPDATE_TOOLTIP'); ?>"
         ><?php echo Text::_('ATTACH_NORMAL_UPDATE') ?></a>
  <?php endif; ?>
  </td>
  </tr>

<?php if ($update == 'file') : ?>
  <tr>
      <td class="key"><label for="upload"><?php echo Text::_('ATTACH_SELECT_FILE_COLON') ?></label></td>
      <td colspan="5"><b><?php echo Text::_('ATTACH_SELECT_NEW_FILE_IF_YOU_WANT_TO_UPDATE_ATTACHMENT_FILE') ?></b><br />
      <input type="file" name="upload" id="upload" size="68" maxlength="1024" />
      </td>
  </tr>
<?php elseif ($update == 'url') : ?>
  <tr>
      <td class="key"><label for="upload" class="hasTip"
          title="<?php echo $this->enter_url_tooltip ?>"><?php echo Text::_('ATTACH_ENTER_URL') ?></label></td>
      <td colspan="5">
         <label for="verify_url"><?php echo Text::_('ATTACH_VERIFY_URL_EXISTENCE') ?></label>
         <input type="checkbox" name="verify_url" value="verify" <?php echo $this->verify_url_checked ?>
                title="<?php echo Text::_('ATTACH_VERIFY_URL_EXISTENCE_TOOLTIP'); ?>" />
     &nbsp;&nbsp;&nbsp;&nbsp;
     <label for="url_relative"><?php echo Text::_('ATTACH_RELATIVE_URL') ?></label>
     <input type="checkbox" name="url_relative" value="relative" <?php echo $this->relative_url_checked ?>
            title="<?php echo Text::_('ATTACH_RELATIVE_URL_TOOLTIP'); ?>" />
         <br />
         <input type="text" name="url" id="upload"
             size="70" title="<?php echo Text::_('ATTACH_ENTER_URL_TOOLTIP'); ?>"
             value="<?php if ($attachment->uri_type == 'url') {
                    echo $attachment->url;
                    } ?>" />
         <br />
         <?php echo Text::_('ATTACH_NOTE_ENTER_URL_WITH_HTTP'); ?>
      </td>
  </tr>
<?php else : ?>
    <?php if ($attachment->uri_type == 'file') : ?>
   <tr>
      <td class="key"><label><?php echo Text::_('ATTACH_FILENAME'); ?></label></td>
      <td colspan="5"><?php echo $attachment->filename; ?>
      <a class="changeButton hasTip" href="<?php echo $this->change_file_url ?>"
         title="<?php echo Text::_('ATTACH_CHANGE_FILE') . '::' . Text::_('ATTACH_CHANGE_FILE_TOOLTIP'); ?>"
         ><?php echo Text::_('ATTACH_CHANGE_FILE') ?></a>
      </td>
  </tr>
  <tr><td class="key"><label><?php echo Text::_('ATTACH_SYSTEM_FILENAME'); ?></label></td>
      <td colspan="5"><?php echo $attachment->filename_sys; ?></td>
  </tr>
  <tr><td class="key"><label><?php echo Text::_('ATTACH_URL_COLON'); ?></label></td>
      <td colspan="5"><?php echo $attachment->url; ?></td>
  </tr>
    <?php elseif ($attachment->uri_type == 'url') : ?>
  <tr>
      <td class="key"><label for="upload"><?php
        if ($attachment->uri_type == 'file') {
            echo Text::_('ATTACH_ENTER_NEW_URL_COLON');
        } else {
            echo Text::_('ATTACH_URL_COLON');
        }
        ?></label></td>
      <td colspan="5">
         <label for="verify_url"><?php echo Text::_('ATTACH_VERIFY_URL_EXISTENCE') ?></label>
         <input type="checkbox" name="verify_url" value="verify" <?php echo $this->verify_url_checked ?>
                title="<?php echo Text::_('ATTACH_VERIFY_URL_EXISTENCE_TOOLTIP'); ?>" />
         &nbsp;&nbsp;&nbsp;&nbsp;
         <label for="url_relative"><?php echo Text::_('ATTACH_RELATIVE_URL') ?></label>
         <input type="checkbox" name="url_relative" value="relative" <?php echo $this->relative_url_checked ?>
                title="<?php echo Text::_('ATTACH_RELATIVE_URL_TOOLTIP'); ?>" />
         <br />
         <input type="text" name="url" id="upload" value="<?php echo $attachment->url; ?>"
                 size="70" title="<?php echo Text::_('ATTACH_ENTER_URL_TOOLTIP'); ?>" />
         <input type="hidden" name="old_url" value="<?php echo $attachment->url; ?>" />
      </td>
   </tr>
   <tr>
     <td class="key"><label for="url_valid"><?php echo Text::_('ATTACH_URL_IS_VALID') ?></label></td>
     <td colspan="5"><?php echo $this->lists['url_valid']; ?></td>
   </tr>
    <?php endif; ?>
<?php endif; ?>

<?php if ((($attachment->uri_type == 'file') and ($update == '')) or ($update == 'file')) : ?>
  <tr><td class="key"><label class="hasTip" for="display_name"
                             title="<?php echo $this->display_filename_tooltip; ?>"
                             ><?php echo Text::_('ATTACH_DISPLAY_FILENAME'); ?></label></td>
      <td colspan="5"><input class="text hasTip" type="text" name="display_name"
                 id="display_name" size="80" maxlength="80"
                 title="<?php echo Text::_('ATTACH_DISPLAY_FILENAME_TOOLTIP'); ?>"
                 value="<?php echo $attachment->display_name;?>"
                 />&nbsp;&nbsp;<?php echo Text::_('ATTACH_OPTIONAL'); ?></td>
  </tr>
<?php elseif ((($attachment->uri_type == 'url') and ($update == '')) or ($update == 'url')) : ?>
  <tr><td class="key"><label class="hasTip" for="display_name"
                             title="<?php echo $this->display_url_tooltip; ?>"
                             ><?php echo Text::_('ATTACH_DISPLAY_URL'); ?></label></td>
      <td colspan="5"><input class="text hasTip" type="text" name="display_name"
                 id="display_name" size="80" maxlength="80"
                 title="<?php echo Text::_('ATTACH_DISPLAY_URL_TOOLTIP'); ?>"
                 value="<?php echo $attachment->display_name;?>"
                 />&nbsp;&nbsp;<?php echo Text::_('ATTACH_OPTIONAL'); ?></td>
  </tr>
<?php endif; ?>

  <tr>
    <td class="key">
        <label class="hasTip" for="description"
               title="<?php echo Text::_('ATTACH_DESCRIPTION') . '::' .
                                 Text::_('ATTACH_DESCRIPTION_DESCRIPTION'); ?>">
              <?php echo Text::_('ATTACH_DESCRIPTION'); ?>
         </label>
     </td>
      <td colspan="5"><input class="text hasTip" type="text" name="description"
             title="<?php echo Text::_('ATTACH_DESCRIPTION_DESCRIPTION'); ?>"
                 id="description" size="80" maxlength="255"
                 value="<?php echo stripslashes($attachment->description);?>" /></td>
  </tr>
<?php if ($this->may_publish) : ?>
  <tr><td class="key"><label><?php echo Text::_('ATTACH_PUBLISHED'); ?></label></td>
      <td colspan="5"><?php echo $this->lists['published']; ?></td>
  </tr>
<?php endif; ?>
  <tr>
    <td class="key">
        <label for="access" class="hasTip"
           title="<?php echo $this->access_level_tooltip ?>">
           <?php echo Text::_('JFIELD_ACCESS_LABEL'); ?>
        </label>
    </td>
    <td colspan="5"><?php echo $this->access_level; ?></td>
  </tr>
  <?php if ($params->get('user_field_1_name', '') != '') : ?>
  <tr><td class="key"><label for="user_field_1"><?php echo $params->get('user_field_1_name'); ?></label></td>
      <td colspan="5"><input class="text" type="text" name="user_field_1"
         id="user_field_1" size="80" maxlength="100"
         value="<?php echo stripslashes($attachment->user_field_1); ?>" /></td>
  </tr>
  <?php endif; ?>
  <?php if ($params->get('user_field_2_name', '') != '') : ?>
  <tr><td class="key"><label for="user_field_2"><?php echo $params->get('user_field_2_name'); ?></label></td>
      <td colspan="5"><input class="text" type="text" name="user_field_2"
         id="user_field_2" size="80" maxlength="100"
         value="<?php echo stripslashes($attachment->user_field_2); ?>" /></td>
  </tr>
  <?php endif; ?>
  <?php if ($params->get('user_field_3_name', '') != '') : ?>
  <tr><td class="key"><label for="user_field_3"><?php echo $params->get('user_field_3_name'); ?></label></td>
      <td colspan="5"><input class="text" type="text" name="user_field_3"
         id="user_field_3" size="80" maxlength="100"
         value="<?php echo stripslashes($attachment->user_field_3); ?>" /></td>
  </tr>
  <?php endif; ?>
  <tr>
      <td class="key"><label for="icon_filename"><?php echo Text::_('ATTACH_ICON_FILENAME'); ?></label></td>
      <td><?php echo $this->lists['icon_filenames']; ?></td>
      <td class="key2"><label><?php echo Text::_('ATTACH_FILE_TYPE'); ?></label></td>
      <?php if ($secure) {
            $ncols = 1;
      } else {
          $ncols = 3;
      }; ?>
      <td colspan="<?php echo $ncols ?>"><?php echo $attachment->file_type; ?></td>
      <?php if ($secure) : ?>
      <td class="key hasTip" title="<?php echo $this->download_count_tooltip; ?>">
          <label for="download_count"><?php echo Text::_('ATTACH_NUMBER_OF_DOWNLOADS'); ?></label></td>
      <td class="hasTip" name="download_count"
          title="<?php echo $this->download_count_tooltip; ?>">
            <?php echo $attachment->download_count ?>
      </td>
      <?php endif; ?>
  </tr>
  <tr>
      <td class="key"><label><?php echo Text::_('ATTACH_FILE_SIZE'); ?></label></td?>
      <td><?php echo $attachment->size_kb; ?> <?php echo Text::_('ATTACH_KB'); ?></td?>
      <td class="key2"><label><?php echo Text::_('ATTACH_DATE_CREATED'); ?></label></td>
      <td><?php echo $created; ?></td>
      <td class="key2"><label><?php echo Text::_('ATTACH_DATE_LAST_MODIFIED'); ?></label></td>
      <td><?php echo $modified; ?></td>
  </tr>
  <tr>
      <td class="key"><label><?php echo Text::_('ATTACH_ATTACHMENT_ID'); ?></label></td>
      <td><?php echo $attachment->id; ?></td>
      <td class="key2"><label><?php echo Text::_('JGLOBAL_FIELD_CREATED_BY_LABEL'); ?></label></td>
      <td><?php echo $attachment->creator_name;?></td>
      <td class="key2"><label><?php echo Text::_('JGLOBAL_FIELD_MODIFIED_BY_LABEL'); ?></label></td>
      <td><?php echo $attachment->modifier_name;?></td>
  </tr>
</tbody>
</table>
</fieldset>
<input type="hidden" name="id" value="<?php echo $attachment->id; ?>" />
<input type="hidden" name="update" value="<?php echo $update; ?>" />
<input type="hidden" name="uri_type" value="<?php echo $attachment->uri_type; ?>" />
<input type="hidden" name="parent_id" id="parent_id" value="<?php echo $parent_id; ?>" />
<input type="hidden" name="parent_type" id="parent_type" value="<?php echo $attachment->parent_type; ?>" />
<input type="hidden" name="parent_entity" id="parent_entity" value="<?php echo $attachment->parent_entity; ?>" />
<input type="hidden" name="old_parent_id" value="<?php echo $attachment->parent_id ?>" />
<input type="hidden" name="old_parent_type" value="<?php echo $attachment->parent_type ?>" />
<input type="hidden" name="old_parent_entity" value="<?php echo $attachment->parent_entity ?>" />
<input type="hidden" name="new_parent_type" id="new_parent_type" value="<?php echo $this->new_parent_type; ?>" />
<input type="hidden" name="new_parent_entity" id="new_parent_entity"
       value="<?php echo $this->new_parent_entity;?>"
/>
<input type="hidden" name="old_display_name" value="<?php echo $attachment->display_name; ?>" />
<input type="hidden" name="option" value="<?php echo $this->option;?>" />
<input type="hidden" name="from" value="<?php echo $this->from;?>" />
<input type="hidden" name="task" value="attachment.save" />
<?php if ($this->in_popup) : ?>
<div class="form_buttons" align="center">
    <input type="submit" name="submit" onclick="javascript: submitbutton('attachment.save')"
           value="<?php echo Text::_('ATTACH_SAVE'); ?>" />
    <span class="right">
        <input type="button" name="cancel"
               value="<?php echo Text::_('ATTACH_CANCEL'); ?>"
               onClick="window.parent.bootstrap.Modal.getInstance(
                         window.parent.document.querySelector('.joomla-modal.show')).hide();" />
     </span>
</div>
<?php endif; ?>
<?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php

// Show the existing attachments (if any)
if ($attachment->parent_id and ($update == 'file')) {
    /** Get the attachments controller class */
    $app = Factory::getApplication();
    /** @var \Joomla\CMS\MVC\Factory\MVCFactory $mvc */
    $mvc = $app->bootComponent("com_attachments")
            ->getMVCFactory();
    /** @var \JMCameron\Component\Attachments\Administrator\Controller\ListController $controller */
    $controller = $mvc->createController('List', 'Administrator', [], $app, $app->getInput());
    $controller->displayString(
        $attachment->parent_id,
        $attachment->parent_type,
        $attachment->parent_entity,
        'ATTACH_EXISTING_ATTACHMENTS',
        false,
        false,
        true,
        $this->from
    );
}
