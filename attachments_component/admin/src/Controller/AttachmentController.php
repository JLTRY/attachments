<?php

/**
 * Attachments component attachment controller
 *
 * @package Attachments
 * @subpackage Attachments_Component
 *
 * @copyright Copyright (C) 2007-2025 Jonathan M. Cameron, All Rights Reserved
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link https://github.com/jmcameron/attachments
 * @author Jonathan M. Cameron
 */

namespace JMCameron\Component\Attachments\Administrator\Controller;

use JMCameron\Component\Attachments\Administrator\Field\AccessLevelsField;
use JMCameron\Component\Attachments\Site\Helper\AttachmentsDefines;
use JMCameron\Component\Attachments\Site\Helper\AttachmentsHelper;
use JMCameron\Component\Attachments\Site\Helper\AttachmentsJavascript;
use JMCameron\Plugin\AttachmentsPluginFramework\AttachmentsPluginManager;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Input\Input;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * Attachment Controller
 *
 * @package Attachments
 */
class AttachmentController extends FormController
{
    /**
     * Constructor.
     *
     * @param   array An optional associative array of configuration settings.
     *
     * @return  FormController
     */
    public function __construct(
        $config = array(),
        MVCFactoryInterface $factory = null,
        ?CMSApplication $app = null,
        ?Input $input = null,
        FormFactoryInterface $formFactory = null
    ) {
        parent::__construct($config, $factory, $app, $input, $formFactory);

        $this->registerTask('applyNew', 'saveNew');
        $this->registerTask('save2New', 'saveNew');
    }


    /**
     * Method to check whether an ID is in the edit list.
     *
     * @param   string  $context    The context for the session storage.
     * @param   int     $id         The ID of the record to add to the edit list.
     *
     * @return  boolean True if the ID is in the edit list.
     */
    protected function checkEditId($context, $id)
    {
        // ??? Do not think this function is used currently
        return true;
    }


    /**
     * Add - Display the form to create a new attachment
     *
     */
    public function add()
    {
        // Fail gracefully if the Attachments plugin framework plugin is disabled
        if (!PluginHelper::isEnabled('attachments', 'framework')) {
            echo '<h1>' . Text::_('ATTACH_WARNING_ATTACHMENTS_PLUGIN_FRAMEWORK_DISABLED') . '</h1>';
            return;
        }

        // Access check.
        $app = $this->app;
        $user = $app->getIdentity();
        if ($user === null || !$user->authorise('core.create', 'com_attachments')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR') . ' (ERR 121)', 403);
        }

        // Access check.
        if (!$this->allowAdd()) {
            // Set the internal error and also the redirect error.
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=' . $this->option .
                                         '&view=' . $this->view_list . $this->getRedirectToListAppend(), false));
            return false;
        }

        $parent_entity = 'default';

        // Get the parent info
        $input = $app->getInput();
        if ($input->getString('article_id')) {
            $pidarr = explode(',', $input->getString('article_id'));
            $parent_type = 'com_content';
        } else {
            $pidarr = explode(',', $input->getString('parent_id', ''));
            $parent_type = $input->getCmd('parent_type', 'com_content');

            // If the entity is embedded in the parent type, split them
            if (strpos($parent_type, '.')) {
                $parts = explode('.', $parent_type);
                $parent_type = $parts[0];
                $parent_entity = $parts[1];
            }
        }

        // Special handling for categories
        if ($parent_type == 'com_categories') {
            $parent_type = 'com_content';
        }

        // Get the parent id and see if the parent is new
        $parent_id = null;
        $new_parent = false;
        if (is_numeric($pidarr[0])) {
            $parent_id = (int)$pidarr[0];
        }
        if ((count($pidarr) == 1) && ($pidarr[0] == '')) {
            // Called from the [New] button
            $parent_id = null;
        }
        if (count($pidarr) > 1) {
            if ($pidarr[1] == 'new') {
                $new_parent = true;
            }
        }

        // Set up the "select parent" button
        PluginHelper::importPlugin('attachments');
        $apm = AttachmentsPluginManager::getAttachmentsPluginManager();
        $entity_info = $apm->getInstalledEntityInfo();
        $parent = $apm->getAttachmentsPlugin($parent_type);

        $parent_entity = $parent->getCanonicalEntityId($parent_entity);
        $parent_entity_name = Text::_('ATTACH_' . $parent_entity);

        if (!$parent_id) {
            // Set up the necessary javascript
            AttachmentsJavascript::setupJavascript();

            $document = $app->getDocument();
            $js = ' 
	   function jSelectParentArticle(id, title, catid, object) {
		   document.getElementById("parent_id").value = id;
		   document.getElementById("parent_title").value = title;
		   let modal = bootstrap.Modal.getInstance(document.getElementById("modal-attachment"));
		   modal.hide();
		   }';
            $document->addScriptDeclaration($js);
        } else {
            if (!is_numeric($parent_id)) {
                $errmsg = Text::sprintf('ATTACH_ERROR_INVALID_PARENT_ID_S', $parent_id) . ' (ERR 122)';
                throw new \Exception($errmsg, 500);
            }
        }

        // Use a component template for the iframe view (from the article editor)
        $from = $input->getWord('from');
        if ($from == 'closeme') {
            $input->set('tmpl', 'component');
        }

        // Disable the main menu items
        $input->set('hidemainmenu', 1);

        // Get the article title
        $parent_title = false;
        if (!$new_parent) {
            PluginHelper::importPlugin('attachments');
            $apm = AttachmentsPluginManager::getAttachmentsPluginManager();
            if (!$apm->attachmentsPluginInstalled($parent_type)) {
                // Exit if there is no Attachments plugin to handle this parent_type
                $errmsg = Text::sprintf('ATTACH_ERROR_INVALID_PARENT_TYPE_S', $parent_type) . ' (ERR 123)';
                throw new \Exception($errmsg, 500);
            }
            $parent = $apm->getAttachmentsPlugin($parent_type);
            $parent_title = $parent->getTitle($parent_id, $parent_entity);
        }

        // Determine the type of upload
        $default_uri_type = 'file';
        $uri_type = $input->getWord('uri', $default_uri_type);
        if (!in_array($uri_type, AttachmentsDefines::$LEGAL_URI_TYPES)) {
            // Make sure only legal values are entered
        }

        // Get the component parameters
        $params = ComponentHelper::getParams('com_attachments');

        // Set up the view
        $document = $app->getDocument();
        $view = $this->getView('Add', $document->getType(), 'Administrator', ['option' => $this->option]);

        $this->addViewUrls($view, 'upload', $parent_id, $parent_type, null, $from);
        // ??? Move the addViewUrls function to attachments base view class

        // We do not have a real attachment yet so fake it
        $attachment = new \stdClass();

        $attachment->uri_type = $uri_type;
        $attachment->state  = $params->get('publish_default', false);
        $attachment->url = '';
        $attachment->url_relative = false;
        $attachment->url_verify = true;
        $attachment->display_name = '';
        $attachment->description = '';
        $attachment->user_field_1 = '';
        $attachment->user_field_2 = '';
        $attachment->user_field_3 = '';
        $attachment->parent_id     = $parent_id;
        $attachment->parent_type   = $parent_type;
        $attachment->parent_entity = $parent_entity;
        $attachment->parent_title  = $parent_title;

        $view->attachment = $attachment;

        $view->parent        = $parent;
        $view->new_parent    = $new_parent;
        $view->may_publish   = $parent->userMayChangeAttachmentState($parent_id, $parent_entity, $user->id);
        $view->entity_info   = $entity_info;
        $view->from          = $from;

        $view->params        = $params;

        // Display the add form
        $view->display();
    }



    /**
     * Save an new attachment
     */
    public function saveNew()
    {
        // Check for request forgeries
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        // Access check.
        $app = $this->app;
        $user = $app->getIdentity();
        if ($user === null || !$user->authorise('core.create', 'com_attachments')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR') . ' (ERR 124)', 403);
        }

        // Make sure we have a user
        if ($user->get('username') == '') {
            $errmsg = Text::_('ATTACH_ERROR_MUST_BE_LOGGED_IN_TO_UPLOAD_ATTACHMENT') . ' (ERR 125)';
            throw new \Exception($errmsg, 500);
        }

        // Get the article/parent handler
        $input = $this->input;
        $new_parent = $input->getBool('new_parent', false);
        $parent_type = $input->getCmd('parent_type', 'com_content');
        $parent_entity = $input->getCmd('parent_entity', 'default');

        // Special handling for categories
        if ($parent_type == 'com_categories') {
            $parent_type = 'com_content';
        }

        // Exit if there is no Attachments plugin to handle this parent_type
        PluginHelper::importPlugin('attachments');
        $apm = AttachmentsPluginManager::getAttachmentsPluginManager();
        if (!$apm->attachmentsPluginInstalled($parent_type)) {
            $errmsg = Text::sprintf('ATTACH_ERROR_INVALID_PARENT_TYPE_S', $parent_type) . ' (ERR 126)';
            throw new \Exception($errmsg, 500);
        }
        $parent = $apm->getAttachmentsPlugin($parent_type);
        $parent_entity = $parent->getCanonicalEntityId($parent_entity);
        $parent_entity_name = Text::_('ATTACH_' . $parent_entity);

        // Make sure we have a valid parent ID
        $parent_id = $input->getInt('parent_id', null);

        if (
            !$new_parent && (($parent_id === 0) ||
                              ($parent_id == null) ||
                              !$parent->parentExists($parent_id, $parent_entity))
        ) {
            // Warn the user to select an article/parent in a popup
            $errmsg = Text::sprintf('ATTACH_ERROR_MUST_SELECT_PARENT_S', $parent_entity_name);
            echo "<script type=\"text/javascript\"> alert('$errmsg'); window.history.go(-1); </script>\n";
            exit();
        }

        // Make sure this user has permission to upload
        if (!$parent->userMayAddAttachment($parent_id, $parent_entity, $new_parent)) {
            $errmsg = Text::sprintf('ATTACH_ERROR_NO_PERMISSION_TO_UPLOAD_S', $parent_entity_name) . ' (ERR 127)';
            throw new \Exception($errmsg, 403);
        }

        // Set up the new record
        /** @var \JMCameron\Component\Attachments\Administrator\Model\AttachmentModel $model */
        $model      = $this->getModel();
        $attachment = $model->getTable();

        if (!$attachment->bind($input->post->getArray())) {
            $errmsg = $attachment->getError() . ' (ERR 128)';
            throw new \Exception($errmsg, 500);
        }
        $attachment->parent_type = $parent_type;
        $parent->new = $new_parent;

        // Note the parents id and title
        if ($new_parent) {
            $attachment->parent_id = null;
            $parent->title = '';
        } else {
            $attachment->parent_id = $parent_id;
            $parent->title = $parent->getTitle($parent_id, $parent_entity);
        }

        // Upload the file!

        // Handle 'from' clause
        $from = $input->getWord('from');

        // See if we are uploading a file or URL
        $new_uri_type = $input->getWord('uri_type');
        if ($new_uri_type && !in_array($new_uri_type, AttachmentsDefines::$LEGAL_URI_TYPES)) {
            // Make sure only legal values are entered
            $new_uri_type = '';
        }

        // If this is a URL, get settings
        $verify_url = false;
        $relative_url = false;
        if ($new_uri_type == 'url') {
            // See if we need to verify the URL (if applicable)
            if ($input->getWord('verify_url') == 'verify') {
                $verify_url = true;
            }
            // Allow relative URLs?
            if ($input->getWord('url_relative') == 'relative') {
                $relative_url = true;
            }
        }

        // Update the url checkbox fields
        $attachment->url_relative = $relative_url ? 1 : 0;
        $attachment->url_verify = $verify_url ? 1 : 0;

        // Update create/modify info
        $attachment->created_by = $user->get('id');
        $attachment->modified_by = $user->get('id');

        PluginHelper::importPlugin('content');
        $app->triggerEvent('onContentBeforeSave', [
            'com_attachments.attachment',
            $attachment,
            null,
            true
        ]);

        // Upload new file/url and create the attachment
        $msg = '';
        $msgType = 'message';
        $error = false;
        if ($new_uri_type == 'file') {
            // Set up the parent entity to save
            $attachment->parent_entity = $parent_entity;

            // Upload a new file
            $result = AttachmentsHelper::uploadFile($attachment, $parent, false, 'upload');
            // NOTE: store() is not needed if uploadFile() is called since it does it

            if (is_object($result)) {
                $error = true;
                $msg = $result->error_msg . ' (ERR 129)';
                $msgType = 'error';
            } else {
                $msg = $result;
            }
        } elseif ($new_uri_type == 'url') {
            // Extra handling for checkboxes for URLs
            $attachment->url_relative = $relative_url;
            $attachment->url_verify = $verify_url;

            // Upload/add the new URL
            $result = AttachmentsHelper::addUrl($attachment, $parent, $verify_url, $relative_url);
            // NOTE: store() is not needed if addUrl() is called since it does it

            if (is_object($result)) {
                $error = true;
                $msg = $result->error_msg . ' (ERR 130)';
                $msgType = 'error';
            } else {
                $msg = $result;
            }
        } else {
            // Set up the parent entity to save
            $attachment->parent_entity = $parent_entity;

            // Save the updated attachment info
            if (!$attachment->store()) {
                $errmsg = $attachment->getError() . ' (ERR 131)';
                throw new \Exception($errmsg, 500);
            }
            $msg = Text::_('ATTACH_ATTACHMENT_UPDATED');
        }

        $app->triggerEvent('onContentAfterSave', [
            'com_attachments.attachment',
            $attachment,
            null,
            true
        ]);

        // See where to go to next
        $task = $this->getTask();

        switch ($task) {
            case 'applyNew':
                if ($error) {
                    $link = 'index.php?option=com_attachments&task=attachment.add&parent_id=' . (int)$parent_id;
                    $link .= "&parent_type={$parent_type}.{$parent_entity}&editor=add_to_parent";
                } else {
                    $link = 'index.php?option=com_attachments&task=attachment.edit&cid[]=' . (int)$attachment->id;
                }
                break;

            case 'save2New':
                if ($error) {
                    $link = 'index.php?option=com_attachments&task=attachment.add&parent_id=' . (int)$parent_id;
                    $link .= "&parent_type={$parent_type}.{$parent_entity}&editor=add_to_parent";
                } else {
                    $link = 'index.php?option=com_attachments&task=attachment.add&parent_id=' . (int)$parent_id;
                    $link .= "&parent_type={$parent_type}.{$parent_entity}&editor=add_to_parent";
                }
                break;

            case 'saveNew':
            default:
                if ($error) {
                    $link = 'index.php?option=com_attachments&task=attachment.add&parent_id=' . (int)$parent_id;
                    $link .= "&parent_type={$parent_type}.{$parent_entity}&editor=add_to_parent";
                } else {
                    $link = 'index.php?option=com_attachments';
                }
                break;
        }

        // If called from the editor, go back to it
        if ($from == 'editor') {
            // ??? This is probably obsolete
            $link = 'index.php?option=com_content&task=edit&cid[]=' . $parent_id;
        }

        // If we are supposed to close this iframe, do it now.
        if ($from == 'closeme') {
            // If there has been a problem, alert the user and redisplay
            if ($msgType == 'error') {
                $errmsg = $msg;
                if (DIRECTORY_SEPARATOR == "\\") {
                    // Fix filename on Windows system so alert can display it
                    $errmsg = str_replace(DIRECTORY_SEPARATOR, "\\\\", $errmsg);
                }
                $errmsg = str_replace("'", "\'", $errmsg);
                $errmsg = str_replace("<br />", "\\n", $errmsg);
                echo "<script type=\"text/javascript\"> alert('$errmsg');  window.history.go(-1); </script>";
                exit();
            }

            // If there is no parent_id, the parent is being created, use the username instead
            if ($new_parent) {
                $pid = 0;
            } else {
                $pid = (int)$parent_id;
            }

            // Close the iframe and refresh the attachments list in the parent window
            $base_url = Uri::base(true);
            $lang = $input->getCmd('lang', '');
            AttachmentsJavascript::closeIframeRefreshAttachments(
                $base_url,
                $parent_type,
                $parent_entity,
                $pid,
                $lang,
                $from
            );
            exit();
        }

        $this->setRedirect($link, $msg, $msgType);
    }


    /**
     * Edit - display the form for the user to edit an attachment
     *
     * @param   string  $key     The name of the primary key of the URL variable (IGNORED)
     * @param   string  $urlVar  The name of the URL variable if different from the primary key. (IGNORED)
     */
    public function edit($key = null, $urlVar = null)
    {
        // Fail gracefully if the Attachments plugin framework plugin is disabled
        if (!PluginHelper::isEnabled('attachments', 'framework')) {
            echo '<h1>' . Text::_('ATTACH_WARNING_ATTACHMENTS_PLUGIN_FRAMEWORK_DISABLED') . '</h1>';
            return;
        }

        // Access check.
        $app = $this->app;
        $user = $app->getIdentity();
        if (
            $user === null or !($user->authorise('core.edit', 'com_attachments') or
               $user->authorise('core.edit.own', 'com_attachments'))
        ) {
            throw new \Exception(Text::_('ATTACH_ERROR_NO_PERMISSION_TO_EDIT') . ' (ERR 132)', 403);
        }

        $uri = Uri::getInstance();

        /** @var \JMCameron\Component\Attachments\Administrator\Model\AttachmentModel $model */
        $model      = $this->getModel();
        $attachment = $model->getTable();

        $input = $app->getInput();
        $cid = $input->get('cid', array(0), 'array');
        $change = $input->getWord('change', '');
        $change_parent = ($change == 'parent');
        $attachment_id = (int)$cid[0];

        // Get the attachment data
        $attachment = $model->getItem($attachment_id);

        $from = $input->getWord('from');
        $layout = $input->getWord('tmpl');

        // Fix the URL for files
        if ($attachment->uri_type == 'file') {
            $attachment->url = $uri->root(true) . '/' . $attachment->url;
        }

        $parent_id = $attachment->parent_id;
        $parent_type = $attachment->parent_type;
        $parent_entity = $attachment->parent_entity;

        // Get the parent handler
        PluginHelper::importPlugin('attachments');
        $apm = AttachmentsPluginManager::getAttachmentsPluginManager();
        if (!$apm->attachmentsPluginInstalled($parent_type)) {
            // Exit if there is no Attachments plugin to handle this parent_type
            $errmsg = Text::sprintf('ATTACH_ERROR_INVALID_PARENT_TYPE_S', $parent_type) . ' (ERR 133)';
            throw new \Exception($errmsg, 500);
        }
        $entity_info = $apm->getInstalledEntityInfo();
        $parent = $apm->getAttachmentsPlugin($parent_type);

        // Get the parent info
        $parent_entity_name = Text::_('ATTACH_' . $parent_entity);
        $parent_title = $parent->getTitle($parent_id, $parent_entity);
        if (!$parent_title) {
            $parent_title = Text::sprintf('ATTACH_NO_PARENT_S', $parent_entity_name);
        }
        $attachment->parent_entity_name = $parent_entity_name;
        $attachment->parent_title = $parent_title;
        $attachment->parent_published = $parent->isParentPublished($parent_id, $parent_entity);
        $update = $input->getWord('update');
        if ($update && !in_array($update, AttachmentsDefines::$LEGAL_URI_TYPES)) {
            $update = false;
        }

        // Set up view for changing parent
        $document = $app->getDocument();
        if ($change_parent) {
            $js = " 
	   function jSelectParentArticle(id, title) {
		   document.getElementById('parent_id').value = id;
		   document.getElementById('parent_title').value = title;
		   window.parent.bootstrap.Modal.getInstance(window.parent.document.querySelector('.joomla-modal.show')).hide();
		   };" ;
            $document->addScriptDeclaration($js);
        }

        // See if a new type of parent was requested
        $new_parent_type = '';
        $new_parent_entity = 'default';
        $new_parent_entity_name = '';
        if ($change_parent) {
            $new_parent_type = $input->getCmd('new_parent_type');
            if ($new_parent_type) {
                if (strpos($new_parent_type, '.')) {
                    $parts = explode('.', $new_parent_type);
                    $new_parent_type = $parts[0];
                    $new_parent_entity = $parts[1];
                }

                $new_parent = $apm->getAttachmentsPlugin($new_parent_type);
                $new_parent_entity = $new_parent->getCanonicalEntityId($new_parent_entity);
                $new_parent_entity_name = Text::_('ATTACH_' . $new_parent_entity);

                // Set up the 'select parent' button
                $selpar_label = Text::sprintf('ATTACH_SELECT_ENTITY_S_COLON', $new_parent_entity_name);
                $selpar_btn_text = '&nbsp;' .
                Text::sprintf(
                    'ATTACH_SELECT_ENTITY_S',
                    $new_parent_entity_name
                ) . '&nbsp;';
                $selpar_btn_tooltip = Text::sprintf('ATTACH_SELECT_ENTITY_S_TOOLTIP', $new_parent_entity_name);

                $selpar_btn_url = $new_parent->getSelectEntityURL($new_parent_entity);
                $selpar_parent_title = '';
                $selpar_parent_id = '-1';
            } else {
                // Set up the 'select parent' button
                $selpar_label = Text::sprintf('ATTACH_SELECT_ENTITY_S_COLON', $attachment->parent_entity_name);
                $selpar_btn_text = '&nbsp;' .
                    Text::sprintf('ATTACH_SELECT_ENTITY_S', $attachment->parent_entity_name) . '&nbsp;';
                $selpar_btn_tooltip = Text::sprintf('ATTACH_SELECT_ENTITY_S_TOOLTIP', $attachment->parent_entity_name);
                $selpar_btn_url = $parent->getSelectEntityURL($parent_entity);
                $selpar_parent_title = $attachment->parent_title;
                $selpar_parent_id = $attachment->parent_id;
            }
        }

        $change_parent_url = $uri->base(true) .
            "/index.php?option=com_attachments&amp;task=attachment.edit&amp;cid[]=$attachment_id&amp;change=parent";
        if ($layout) {
            $change_parent_url .= "&amp;from=$from&amp;tmpl=$layout";
        }

        // Get the component parameters
        $params = ComponentHelper::getParams('com_attachments');

        // Set up the view
        $document = $app->getDocument();
        $view = $this->getView('Edit', $document->getType(), 'Administrator', ['option' => $this->option]);

        $this->addViewUrls(
            $view,
            'update',
            $parent_id,
            $parent_type,
            $attachment_id,
            $from
        );

        // Update change URLS to remember if we want to change the parent
        if ($change_parent) {
            $view->change_file_url   .= "&amp;change=parent&amp;new_parent_type=$new_parent_type";
            $view->change_url_url    .= "&amp;change=parent&amp;new_parent_type=$new_parent_type";
            $view->normal_update_url .= "&amp;change=parent&amp;new_parent_type=$new_parent_type";
            if ($new_parent_entity != 'default') {
                $view->change_file_url   .= ".$new_parent_entity";
                $view->change_url_url    .= ".$new_parent_entity";
                $view->normal_update_url .= ".$new_parent_entity";
            }
        }

        // Add a few necessary things for iframe popups
        if ($layout) {
            $view->change_file_url   .= "&amp;from=$from&amp;tmpl=$layout";
            $view->change_url_url    .= "&amp;from=$from&amp;tmpl=$layout";
            $view->normal_update_url .= "&amp;from=$from&amp;tmpl=$layout";
        }

        // Suppress the display filename if we are switching from file to url
        $display_name = $attachment->display_name;
        if ($update && ($update != $attachment->uri_type)) {
            $attachment->display_name = '';
        }

        // Handle iframe popup requests
        $known_froms = $parent->knownFroms();
        $in_popup = false;
        $save_url = 'index.php';
        if (in_array($from, $known_froms)) {
            $in_popup = true;
            AttachmentsJavascript::setupJavascript();
            $save_url = 'index.php?option=com_attachments&amp;task=attachment.save';
        }
        $view->save_url = $save_url;
        $view->in_popup = $in_popup;

        // Set up the access field
        $view->access_level_tooltip = Text::_('JFIELD_ACCESS_LABEL') . '::' . Text::_('JFIELD_ACCESS_DESC');
        $view->access_level = AccessLevelsField::getAccessLevels('access', 'access', $attachment->access);

        // Set up view info
        $view->update            = $update;
        $view->change_parent     = $change_parent;
        $view->new_parent_type   = $new_parent_type;
        $view->new_parent_entity = $new_parent_entity;
        $view->change_parent_url = $change_parent_url;
        $view->entity_info       = $entity_info;
        $view->may_publish       = $parent->userMayChangeAttachmentState($parent_id, $parent_entity, $user->id);

        $view->from       = $from;

        $view->attachment = $attachment;

        $view->parent     = $parent;
        $view->params     = $params;

        // Set up for selecting a new type of parent
        if ($change_parent) {
            $view->selpar_label =       $selpar_label;
            $view->selpar_btn_text =        $selpar_btn_text;
            $view->selpar_btn_tooltip =     $selpar_btn_tooltip;
            $view->selpar_btn_url =         $selpar_btn_url;
            $view->selpar_parent_title =  $selpar_parent_title;
            $view->selpar_parent_id =   $selpar_parent_id;
        }

        $view->display();
    }


    /**
     * Save an attachment (from editing)
     */
    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        // Access check.
        $app = $this->app;
        $user = $app->getIdentity();
        if (
            $user === null or !($user->authorise('core.edit', 'com_attachments') or
               $user->authorise('core.edit.own', 'com_attachments'))
        ) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR') . ' (ERR 134)', 403);
        }

        /** @var \JMCameron\Component\Attachments\Administrator\Model\AttachmentModel $model */
        $model      = $this->getModel();
        $attachment = $model->getTable();

        // Make sure the article ID is valid
        $input = $app->getInput();
        $attachment_id = $input->getInt('id');
        if (!$attachment->load($attachment_id)) {
            $errmsg = Text::sprintf(
                'ATTACH_ERROR_CANNOT_UPDATE_ATTACHMENT_INVALID_ID_N',
                $attachment_id
            ) . ' (ERR 135)';
            throw new \Exception($errmsg, 500);
        }

        // Note the old uri type
        $old_uri_type = $attachment->uri_type;

        // Get the data from the form
        if (!$attachment->bind($input->post->getArray())) {
            $errmsg = $attachment->getError() . ' (ERR 136)';
            throw new \Exception($errmsg, 500);
        }

        // Get the parent handler for this attachment
        PluginHelper::importPlugin('attachments');
        PluginHelper::importPlugin('content');
        $app->triggerEvent('onContentBeforeSave', [
            'com_attachments.attachment',
            $attachment,
            null,
            false
        ]);

        $apm = AttachmentsPluginManager::getAttachmentsPluginManager();
        if (!$apm->attachmentsPluginInstalled($attachment->parent_type)) {
            $errmsg = Text::sprintf('ATTACH_ERROR_INVALID_PARENT_TYPE_S', $attachment->parent_type) . ' (ERR 135B)';
            throw new \Exception($errmsg, 500);
        }
        $parent = $apm->getAttachmentsPlugin($attachment->parent_type);

        // See if the parent ID has been changed
        $parent_changed = false;
        $old_parent_id = $input->getString('old_parent_id');
        if ($old_parent_id == '') {
            $old_parent_id = null;
        } else {
            $old_parent_id = $input->getInt('old_parent_id');
        }

        // Handle new parents (in process of creation)
        if ($parent->newParent($attachment)) {
            $attachment->parent_id = null;
        }

        // Deal with updating an orphaned attachment
        if (($old_parent_id == null) && is_numeric($attachment->parent_id)) {
            $parent_changed = true;
        }

        // Check for normal parent changes
        if ($old_parent_id && ( $attachment->parent_id != $old_parent_id )) {
            $parent_changed = true;
        }

        // See if we are updating a file or URL
        $new_uri_type = $input->getWord('update');
        if ($new_uri_type && !in_array($new_uri_type, AttachmentsDefines::$LEGAL_URI_TYPES)) {
            // Make sure only legal values are entered
            $new_uri_type = '';
        }

        // See if the parent type has changed
        $new_parent_type = $input->getCmd('new_parent_type');
        $new_parent_entity = $input->getCmd('new_parent_entity');
        $old_parent_type = $input->getCmd('old_parent_type');
        $old_parent_entity = $input->getCmd('old_parent_entity');
        if (
            ($new_parent_type &&
              (($new_parent_type != $old_parent_type) ||
               ($new_parent_entity != $old_parent_entity)))
        ) {
            $parent_changed = true;
        }

        // If the parent has changed, make sure they have selected the new parent
        if ($parent_changed && ( (int)$attachment->parent_id == -1 )) {
            $errmsg = Text::sprintf('ATTACH_ERROR_MUST_SELECT_PARENT');
            echo "<script type=\"text/javascript\"> alert('$errmsg'); window.history.go(-1); </script>\n";
            exit();
        }

        // If the parent has changed, switch the parent, rename files if necessary
        if ($parent_changed) {
            if (($new_uri_type == 'url') && ($old_uri_type == 'file')) {
                // If we are changing parents and converting from file to URL, delete the old file

                // Load the attachment so we can get its filename_sys
                $db = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true);
                $query->select('filename_sys, id')->from('#__attachments')->where('id=' . (int)$attachment->id);
                $db->setQuery($query, 0, 1);
                $filename_sys = $db->loadResult();
                File::delete($filename_sys);
                AttachmentsHelper::cleanDirectory($filename_sys);
            } else {
                // Otherwise switch the file/url to the new parent
                if ($old_parent_id == null) {
                    $old_parent_id = 0;
                    // NOTE: When attaching a file to an article during creation,
                    //       the article_id (parent_id) is initially null until
                    //       the article is saved (at that point the
                    //       parent_id/article_id updated).  If the attachment is
                    //       added and creating the article is canceled, the
                    //       attachment exists but is orphaned since it does not
                    //       have a parent.  It's article_id is null, but it is
                    //       saved in directory as if its article_id is 0:
                    //       article/0/file.txt.  Therefore, if the parent has
                    //       changed, we pretend the old_parent_id=0 for file
                    //       renaming/moving.
                }

                $error_msg = AttachmentsHelper::switchParent(
                    $attachment,
                    $old_parent_id,
                    $attachment->parent_id,
                    $new_parent_type,
                    $new_parent_entity
                );
                if ($error_msg != '') {
                    $errmsg = Text::_($error_msg) . ' (ERR 137)';
                    $link = 'index.php?option=com_attachments';
                    $this->setRedirect($link, $errmsg, 'error');
                    return;
                }
            }
        }

        // Update parent type/entity, if needed
        if ($new_parent_type && ($new_parent_type != $old_parent_type)) {
            $attachment->parent_type = $new_parent_type;
        }
        if ($new_parent_type && ($new_parent_entity != $old_parent_entity)) {
            $attachment->parent_entity = $new_parent_entity;
        }

        // Get the article/parent handler
        if ($new_parent_type) {
            $parent_type = $new_parent_type;
            $parent_entity = $new_parent_entity;
        } else {
            $parent_type = $input->getCmd('parent_type', 'com_content');
            $parent_entity = $input->getCmd('parent_entity', 'default');
        }
        $parent = $apm->getAttachmentsPlugin($parent_type);
        $parent_entity = $parent->getCanonicalEntityId($parent_entity);

        // Get the title of the article/parent
        $new_parent = $input->getBool('new_parent', false);
        $parent->new = $new_parent;
        if ($new_parent) {
            $attachment->parent_id = null;
            $parent->title = '';
        } else {
            $parent->title = $parent->getTitle($attachment->parent_id, $parent_entity);
        }

        // Check to make sure the user has permissions to edit the attachment
        if (!$parent->userMayEditAttachment($attachment)) {
            // ??? Add better error message
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR') . ' (ERR 139)', 403);
        }

        // Double-check to see if the URL changed
        $old_url = $input->getString('old_url');
        if (!$new_uri_type && $old_url && ($old_url != $attachment->url)) {
            $new_uri_type = 'url';
        }

        // If this is a URL, get settings
        $verify_url = false;
        $relative_url = false;
        if ($new_uri_type == 'url') {
            // See if we need to verify the URL (if applicable)
            if ($input->getWord('verify_url') == 'verify') {
                $verify_url = true;
            }
            // Allow relative URLs?
            if ($input->getWord('url_relative') == 'relative') {
                $relative_url = true;
            }
        }

        // Compute the update time
        $now = Factory::getDate();

        // Update create/modify info
        $attachment->modified_by = $user->get('id');
        $attachment->modified = $now->toSql();

        // Upload new file/url and create/update the attachment
        $msg = null;
        $msgType = 'message';
        if ($new_uri_type == 'file') {
            // Upload a new file
            $result = AttachmentsHelper::uploadFile($attachment, $parent, $attachment_id, 'update');
            if (is_object($result)) {
                $msg = $result->error_msg . ' (ERR 140)';
                $msgType = 'error';
            } else {
                $msg = $result;
            }
            // NOTE: store() is not needed if uploadFile() is called since it does it
        } elseif ($new_uri_type == 'url') {
            // Upload/add the new URL
            $result = AttachmentsHelper::addUrl(
                $attachment,
                $parent,
                $verify_url,
                $relative_url,
                $old_uri_type,
                $attachment_id
            );

            // NOTE: store() is not needed if addUrl() is called since it does it
            if (is_object($result)) {
                $msg = $result->error_msg . ' (ERR 141)';
                $msgType = 'error';
            } else {
                $msg = $result;
            }
        } else {
            // Extra handling for checkboxes for URLs
            if ($attachment->uri_type == 'url') {
                // Update the url_relative field
                $attachment->url_relative = $relative_url;
                $attachment->url_verify = $verify_url;
            }

            // Remove any extraneous fields
            if (isset($attachment->parent_entity_name)) {
                unset($attachment->parent_entity_name);
            }

            // Save the updated attachment info
            if (!$attachment->store()) {
                $errmsg = $attachment->getError() . ' (ERR 142)';
                throw new \Exception($errmsg, 500);
            }
        }

        $app->triggerEvent('onContentAfterSave', [
            'com_attachments.attachment',
            $attachment,
            null,
            false
        ]);

        switch ($this->getTask()) {
            case 'apply':
                if (!$msg) {
                    $msg = Text::_('ATTACH_CHANGES_TO_ATTACHMENT_SAVED');
                }
                $link = 'index.php?option=com_attachments&task=attachment.edit&cid[]=' . (int)$attachment->id;
                break;

            case 'save':
            default:
                if (!$msg) {
                    $msg = Text::_('ATTACH_ATTACHMENT_UPDATED');
                }
                $link = 'index.php?option=com_attachments';
                break;
        }

        // If invoked from an iframe popup, close it and refresh the attachments list
        $from = $input->getWord('from');
        $known_froms = $parent->knownFroms();
        if (in_array($from, $known_froms)) {
            // If there has been a problem, alert the user and redisplay
            if ($msgType == 'error') {
                $errmsg = $msg;
                if (DIRECTORY_SEPARATOR == "\\") {
                    // Fix filename on Windows system so alert can display it
                    $errmsg = str_replace(DIRECTORY_SEPARATOR, "\\\\", $errmsg);
                }
                $errmsg = str_replace("'", "\'", $errmsg);
                $errmsg = str_replace("<br />", "\\n", $errmsg);
                echo "<script type=\"text/javascript\"> alert('$errmsg');  window.history.go(-1); </script>";
                exit();
            }

            // Can only refresh the old parent
            if ($parent_changed) {
                $parent_type = $old_parent_type;
                $parent_entity = $old_parent_entity;
                $parent_id = $old_parent_id;
            } else {
                $parent_id = (int)$attachment->parent_id;
            }

            // Close the iframe and refresh the attachments list in the parent window
            $uri = Uri::getInstance();
            $base_url = $uri->base(true);
            $lang = $input->getCmd('lang', '');
            AttachmentsJavascript::closeIframeRefreshAttachments(
                $base_url,
                $parent_type,
                $parent_entity,
                $parent_id,
                $lang,
                $from
            );
            exit();
        }

        $this->setRedirect($link, $msg, $msgType);
    }



    /**
     * Add the save/upload/update urls to the view
     *
     * @param &object &$view the view to add the urls to
     * @param string $save_type type of save ('file' or 'url')
     * @param int $parent_id id for the parent
     $ @param string $parent_type type of parent (eg, com_content)
     * @param int $attachment_id id for the attachment
     * @param string $from the from ($option) value
     */
    private function addViewUrls(
        &$view,
        $save_type,
        $parent_id,
        $parent_type,
        $attachment_id,
        $from
    ) {
        // Construct the url to save the form
        $url_base = "index.php?option=com_attachments";

        // $template = '&tmpl=component';
        $template = '';
        $add_task  = 'attachment.add';
        $edit_task = 'attachment.edit';
        // $idinfo = "&id=$attachment_id";
        $idinfo = "&cid[]=$attachment_id";
        $parentinfo = '';
        if ($parent_id) {
            $parentinfo = "&parent_id=$parent_id&parent_type=$parent_type";
        }

        $save_task = 'attachment.save';
        if ($save_type == 'upload') {
            $save_task = 'attachment.saveNew';
        }

        // Handle the main save URL
        $save_url = $url_base . "&task=" . $save_task . $template;
        if ($from == 'closeme') {
            // Keep track of what are supposed to do after saving
            $save_url .= "&from=closeme";
        }
        $view->save_url = Route::_($save_url);

        // Construct the URL to upload a URL instead of a file
        if ($save_type == 'upload') {
            $upload_file_url = $url_base . "&task=$add_task&uri=file" . $parentinfo . $template;
            $upload_url_url  = $url_base . "&task=$add_task&uri=url" . $parentinfo . $template;

            // Keep track of what are supposed to do after saving
            if ($from == 'closeme') {
                $upload_file_url .= "&from=closeme";
                $upload_url_url .= "&from=closeme";
            }

            // Add the URL
            $view->upload_file_url = Route::_($upload_file_url);
            $view->upload_url_url  = Route::_($upload_url_url);
        } elseif ($save_type == 'update') {
            $change_url = $url_base . "&task=$edit_task" . $idinfo;
            $change_file_url =  $change_url . "&amp;update=file" . $template;
            $change_url_url =  $change_url . "&amp;update=url" . $template;
            $normal_update_url =  $change_url . $template;

            // Keep track of what are supposed to do after saving
            if ($from == 'closeme') {
                $change_file_url .= "&from=closeme";
                $change_url_url .= "&from=closeme";
                $normal_update_url .= "&from=closeme";
            }

            // Add the URLs
            $view->change_file_url   = Route::_($change_file_url);
            $view->change_url_url    = Route::_($change_url_url);
            $view->normal_update_url = Route::_($normal_update_url);
        }
    }


    /**
     * Download an attachment
     */
    public function download()
    {
        // Get the attachment ID
        $id = Factory::getApplication()->getInput()->getInt('id');
        if (!is_numeric($id)) {
            $errmsg = Text::sprintf('ATTACH_ERROR_INVALID_ATTACHMENT_ID_N', $id) . ' (ERR 143)';
            throw new \Exception($errmsg, 500);
        }

        // NOTE: AttachmentsHelper::downloadAttachment($id) checks access permission
        AttachmentsHelper::downloadAttachment($id);
    }




    /**
     * Put up a dialog to double-check before deleting an attachment
     */
    public function deleteWarning()
    {
        // Access check.
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (
            $user === null or !( $user->authorise('core.delete', 'com_attachments') or
                $user->authorise('attachments.delete.own', 'com_attachments') )
        ) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR') . ' (ERR 144)', 403);
        }

        // Make sure we have a valid attachment ID
        $input = $app->getInput();
        $attachment_id = $input->getInt('id', null);
        if (is_numeric($attachment_id)) {
            $attachment_id = (int)$attachment_id;
        } else {
            $errmsg = Text::sprintf(
                'ATTACH_ERROR_CANNOT_DELETE_INVALID_ATTACHMENT_ID_N',
                $attachment_id
            ) . ' (ERR 145)';
            throw new \Exception($errmsg, 500);
        }

        // Load the attachment
        /** @var \JMCameron\Component\Attachments\Administrator\Model\AttachmentModel $model */
        $model      = $this->getModel();
        $attachment = $model->getTable();

        // Make sure the article ID is valid
        $attachment_id = $input->getInt('id');
        if (!$attachment->load($attachment_id)) {
            $errmsg = Text::sprintf('ATTACH_ERROR_CANNOT_DELETE_INVALID_ATTACHMENT_ID_N', $attachment_id) .
            ' (ERR 146)';
            throw new \Exception($errmsg, 500);
        }

        // Set up the view
        $document = $app->getDocument();
        $view = $this->getView(
            'Warning',
            $document->getType(),
            'Administrator',
            ['option' => $input->getCmd('option')]
        );
        $view->parent_id = $attachment_id;
        $view->from = $input->getWord('from');
        $view->tmpl = $input->getWord('tmpl');

        // Prepare for the query
        $view->warning_title = Text::_('ATTACH_WARNING');
        if ($attachment->uri_type == 'file') {
            $msg = "( {$attachment->filename} )";
        } else {
            $msg = "( {$attachment->url} )";
        }
        $view->warning_question = Text::_('ATTACH_REALLY_DELETE_ATTACHMENT') . '<br/>' . $msg;
        $view->action_button_label = Text::_('ATTACH_DELETE');

        $view->action_url = "index.php?option=com_attachments&amp;task=attachments.delete&amp;cid[]="
        . (int)$attachment_id;
        $view->action_url .= "&amp;from=" . $view->from;

        $view->display();
    }

    public function cancel($key = null)
    {
        $this->setRedirect(Route::_('index.php?option=' . $this->option, false));
    }
}
