<?php
/**
 * @package     Plugin.Content
 *
 * @copyright   Copyright (C) 2018 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see COPYING
 */
defined('_JEXEC') or die;

use Joomla\Registry\Registry;

/**
 * Publish content to the Po.et ledger.
 */
class PlgContentPoet extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Manage Po.et publishing from the article web form.
     */
    public function onContentPrepareForm($form, $data)
    {
        if (!($form instanceof JForm)) {
            $this->_subject->setError('JERROR_NOT_A_FORM');

            return false;
        }

        // Check we are manipulating a valid form.
        $name = $form->getName();

        if (!in_array($name, array('com_content.article', 'com_content.form'))) {
            return true;
        }

        $metadata = new Registry($data->metadata);

        // if the po.et work id is available, display it.
        if ($metadata->get('poetWorkId')) {
            JForm::addFormPath(__DIR__.'/forms');
            $form->loadFile('poet', false);
        }
    }

    /**
     * Publish article to the Po.et ledger when it is saved.
     *
     * @param   string   $context  The context of the content passed to the plugin.
     * @param   object   $item     A JTableContent object
     * @param   boolean  $isNew    If the content is about to be created.
     *
     * @return  boolean  True if the content is successfully published to
     *                   Po.et, false otherwise.
     */
    public function onContentAfterSave($context, $item, $isNew)
    {
        // only publish articles to the blockchain.
        if ($context === 'com_content.article' || $context === 'com_content.form') {
            // only place published articles on the blockchain.
            if ($item->state == 1) {
                return $this->publish($item);
            }
        }

        return true;
    }

    /**
     * Publish to Po.et when the state of one or more articles changes to Published.
     *
     * @param   string   $context  The context for the content passed to the plugin.
     * @param   array    $pks      A list of primary key ids of the content that has changed state.
     * @param   integer  $value    The value of the state that the content has been changed to.
     *
     * @return  boolean  True if all articles were successfully published, false otherwise.
     */
    public function onContentChangeState($context, $pks, $value)
    {
        $app = JFactory::getApplication();

        // only publish articles to the blockchain.
        if ($context === 'com_content.article') {
            if ($value == 1) {
                JTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_content/tables/');
                $table = JTable::getInstance('Content');

                foreach ($pks as $pk) {
                    if ($table->load($pk)) {
                        if ($this->publish($table) === false) {
                            $return = false;
                            $app->enqueueMessage(JText::_('PLG_CONTENT_POET_ERR_POET_ITEMS_NOT_PUBLISHED'), 'error');
                        }
                    } else {
                        $return = false;
                        $app->enqueueMessage(JText::_('PLG_CONTENT_POET_ERR_ITEMS_NOT_LOADED'), 'error');
                    }
                }
            }
        }

        return $return;
    }

    public function onContentAfterDisplay($context, &$item, &$params, $page = 0)
    {
        // Get the path for the voting form layout file
        $path = JPluginHelper::getLayoutPath('content', 'poet', 'poet');

        $metadata = $item->metadata;

        if (!is_a($metadata, "\Joomla\Registry\Registry")) {
            $metadata = new Registry($item->metadata);
        }

        $workId = $metadata->get('poetWorkId');

        // if article has a poet work id, show badge.
        if ($workId) {
            ob_start();
            include $path;
            $html = ob_get_clean();
        }

        return $html;
    }

    /**
     * Publish an article to the Po.et ledger.
     *
     * @param   object   $item  A JTableContent object
     *
     * @return  boolean  True if the content is successfully published to
     *                   Po.et, false otherwise.
     */
    private function publish($item)
    {
        $app = JFactory::getApplication();

        $url = new JUri($this->params->get('frost_api_url', 'https://api.frost.po.et').'/works');
        $token = $this->params->get('frost_api_token');

        if (!$token) {
            $app->enqueueMessage(JText::_('PLG_CONTENT_POET_ERR_NO_FROST_API_TOKEN'), 'error');
            return false;
        }

        $articletext = trim($item->fulltext) != '' ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;

        switch ($this->params->get('author')) {
            case 'site_name':
                $author = $app->get('site_name');
                break;

            case 'global':
                $author = $this->params->get('global');
                break;

            default:
                $author = $item->created_by_alias ? $item->created_by_alias : JFactory::getUser($item->created_by)->name;
                break;
        }

        if (!trim($author)) {
            $app->enqueueMessage(JText::_('PLG_CONTENT_POET_ERR_NO_AUTHOR'), 'error');
            return false;
        }

        $tagsHelper = new JHelperTags;

        $tags = array_map(
            function($tag) {
                return $tag->title;
            },
            $tagsHelper->getItemTags('com_content.article' , $item->id));

        $data =
        [
            'name'=>$item->title,
            'content'=>$articletext,
            'author'=>$author,
            'dateCreated'=>JFactory::getDate($item->created)->toISO8601(),
            'datePublished'=>JFactory::getDate($item->publish_up)->toISO8601(),
        ];

        if (count($tags) > 0) {
            $data['tags'] = implode(",", $tags);
        }

        $headers =
        [
            'token'=>$this->params->get('frost_api_token'),
            'Content-Type'=>'application/json'
        ];

        $http = JHttpFactory::getHttp();

        try {
            $response = $http->post((string)$url, json_encode($data), $headers, 30);

            if ($response->code = 200) {
                $body = json_decode($response->body);

                $metadata = new Registry($item->metadata);
                $metadata->set('poetWorkId', $body->workId);

                $item->metadata = (string)$metadata;

                if (!$item->store()) {
                    throw new Exception(JText::_('PLG_CONTENT_POET_ERR_POET_WORKID_NOT_SAVED'), 500);
                }
            } else {
                throw new Exception($response->body, $response->code);
            }
        } catch (Exception $e) {
            $app->enqueueMessage(JText::_('PLG_CONTENT_POET_ERR_CATCHALL'), 'error');
            JLog::add("Plugin (Content - Poet): ".$e->getMessage(), JLog::ERROR, 'error');
            return false;
        }

        return true;
    }
}
