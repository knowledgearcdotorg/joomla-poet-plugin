<?php
/**
 * @copyright   Copyright (C) 2018 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see COPYING
 */
defined('_JEXEC') or die;
?>
<div style="width: 165px; height: 50px; background-color: white; font-family: Roboto; font-size: 12px; border: 1px solid #CDCDCD; border-radius: 4px; box-shadow: 0 2px 0 0 #F0F0F0;">
<!--   @TODO add link to po.et Work verification with target="_blank"   -->
    <div style="color: #35393E; text-decoration: none; display: flex; flex-direction: row;  height: 50px">
        <img src="<?php echo JUri::base(); ?>/media/content/poet/quill.svg"
             style=" width: 31px; height: 31px; margin-top: 8px; margin-left: 8px; margin-right: 8px; color: #35393E; font-family: Roboto;">
        <div>
            <p title="<?php echo $workId; ?>" style="padding-top: 10px; line-height: 15px; margin: 0; font-size: 10pt; font-weight: bold; text-align: left;"><?php echo JText::_('PLG_CONTENT_POET_VERIFIED_ON_POET'); ?></p>
        </div>
    </div>
</div>
