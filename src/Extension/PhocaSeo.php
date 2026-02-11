<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

declare(strict_types=1);

namespace Phoca\Plugin\System\PhocaSeo\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Phoca\Component\PhocaSeo\Administrator\Helper\PhocaSeoHelper;
use Phoca\Component\PhocaSeo\Administrator\Helper\SeoRulesHelper;

defined('_JEXEC') or die;

/**
 * Phoca SEO System Plugin
 *
 * @since  6.0.0
 */
class PhocaSeo extends CMSPlugin implements SubscriberInterface
{
    protected $dbo;
    protected $autoloadLanguage = true;

    public function setDatabase(DatabaseInterface $db): void {
        $this->dbo = $db;
    }

    public static function getSubscribedEvents(): array {
        return [
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onContentAfterSave'   => 'onContentAfterSave',
            'onAfterRender'        => 'onAfterRender',
        ];
    }

    public function onAfterRender(): void
    {
        /** @var \Joomla\CMS\Application\AdministratorApplication $app */
        $app = Factory::getApplication();

        // Only run in admin,com_content,edit view
        if (!$app->isClient('administrator')) return;

        $input = $app->input;
        if ($input->get('option') !== 'com_content' || $input->get('view') !== 'article' || $input->get('layout') !== 'edit') {
            return;
        }

        // Load language for manual injection
        $lang = Factory::getLanguage();
        $lang->load('com_phocaseo', JPATH_ADMINISTRATOR);

        // Get Article ID and Canonical Routes
        $itemId = $input->getInt('id', 0);
        $canonicalRoutes = [];
        if ($itemId > 0) {
            $canonicalRoutes = PhocaSeoHelper::getCanonicalRoutes($itemId);
        }

        // Render the sidebar
        ob_start();
        $layoutPath = JPATH_ADMINISTRATOR . '/components/com_phocaseo/layouts/phocaseo/sidebar.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        }
        $sidebarHtml = ob_get_clean();

        if (empty($sidebarHtml)) return;

        // Wrap in container
        $html = '<div id="phoca-seo-sidebar-container" class="d-none">' . $sidebarHtml . '</div>';

        // Inject before closing body (case-insensitive)
        $body = $app->getBody();
        $body = preg_replace('/<\/body>/i', $html . '</body>', $body);
        $app->setBody($body);
    }

    public function onContentPrepareForm(PrepareFormEvent $event): bool {
        $form = $event->getForm();
        $data = $event->getData();
        $app  = Factory::getApplication();

        if (!$app->isClient('administrator') || !in_array($form->getName(), ['com_content.article'])) {
            return true;
        }

        $lang = Factory::getLanguage();
        $lang->load('com_phocaseo', JPATH_ADMINISTRATOR);


        $params = ComponentHelper::getParams('com_phocaseo');
        $phocaSeoParams = [
            'enable_keyword_variations'          => (int) $params->get('enable_keyword_variations', 0),
            'variation_strategy'                 => $params->get('variation_strategy', 'suffix'),
            'variation_language'                 => $params->get('variation_language', ''),
            'variation_include_in_deep_analysis' => (int) $params->get('variation_include_in_deep_analysis', 1),
        ];

        Factory::getApplication()->getDocument()->addScriptOptions('com_phocaseo.params', $phocaSeoParams);

        // Expose strings to JS
        Text::script('COM_PHOCASEO_JS_SCANNING_LINKS');
        Text::script('COM_PHOCASEO_JS_LINKS_SCANNED');
        Text::script('COM_PHOCASEO_JS_SCAN_FAILED');
        Text::script('COM_PHOCASEO_JS_ERROR_SCANNING');
        Text::script('COM_PHOCASEO_JS_FIELD_EMPTY');
        Text::script('COM_PHOCASEO_JS_SET_FOCUS_KEYWORD');
        Text::script('COM_PHOCASEO_JS_FILL_SCHEMA');
        Text::script('COM_PHOCASEO_JS_MISSING_SCHEMA');
        Text::script('COM_PHOCASEO_JS_KEYWORD_REQUIRED');
        Text::script('COM_PHOCASEO_JS_STARTING_DEEP_ANALYSIS');
        Text::script('COM_PHOCASEO_JS_ANALYSIS_COMPLETE');
        Text::script('COM_PHOCASEO_JS_CONNECTION_FAILED');
        Text::script('COM_PHOCASEO_JS_TITLE_PLACEHOLDER');
        Text::script('COM_PHOCASEO_JS_DESC_PLACEHOLDER');
        Text::script('COM_PHOCASEO_JS_DESCRIPTION_PLACEHOLDER');
        Text::script('COM_PHOCASEO_JS_SUCCESS');
        Text::script('COM_PHOCASEO_JS_NO_CONTENT');
        Text::script('COM_PHOCASEO_JS_NO_IMAGES_TO_FIX');
        Text::script('COM_PHOCASEO_JS_ERROR_FIXING_IMAGES');
        Text::script('COM_PHOCASEO_JS_FIXING_IMAGES');
        Text::script('COM_PHOCASEO_JS_ALTS_FIXED');
        Text::script('COM_PHOCASEO_JS_AI_NO_ALTS');
        Text::script('COM_PHOCASEO_JS_AI_ERROR');
        Text::script('COM_PHOCASEO_RULE_TITLE_LABEL');
        Text::script('COM_PHOCASEO_RULE_DESCRIPTION_LABEL');
        Text::script('COM_PHOCASEO_RULE_KEYWORDS_LABEL');
        Text::script('COM_PHOCASEO_KEYWORDS');
        Text::script('COM_PHOCASEO_RULE_ALIAS_LABEL');
        Text::script('COM_PHOCASEO_RULE_CONTENT_LABEL');
        Text::script('COM_PHOCASEO_RULE_SCHEMA_LABEL');
        Text::script('COM_PHOCASEO_RULE_SCHEMA_TYPE_LABEL');
        Text::script('COM_PHOCASEO_RULE_SCHEMA_MANDATORY_LABEL');
        Text::script('COM_PHOCASEO_RULE_TITLE_LENGTH');
        Text::script('COM_PHOCASEO_RULE_TITLE_KEYWORD');
        Text::script('COM_PHOCASEO_RULE_TITLE_KEYWORD_START');
        Text::script('COM_PHOCASEO_RULE_DESC_LENGTH');
        Text::script('COM_PHOCASEO_RULE_DESC_KEYWORD');
        Text::script('COM_PHOCASEO_RULE_KEYWORDS_LENGTH');
        Text::script('COM_PHOCASEO_RULE_KEYWORDS_COUNT');
        Text::script('COM_PHOCASEO_RULE_SLUG_KEYWORD');
        Text::script('COM_PHOCASEO_RULE_KEYWORD_DENSITY');
        Text::script('COM_PHOCASEO_RULE_SUBHEADINGS');
        Text::script('COM_PHOCASEO_RULE_H_PLACEMENT');
        Text::script('COM_PHOCASEO_RULE_IMG_ALTS');
        Text::script('COM_PHOCASEO_RULE_INTRO_KEYWORD');
        Text::script('COM_PHOCASEO_RULE_OUTBOUND_LINKS');
        Text::script('COM_PHOCASEO_RULE_INTERNAL_LINKS');
        Text::script('COM_PHOCASEO_RULE_READABILITY');
        Text::script('COM_PHOCASEO_RULE_TRANSITION_WORDS');
        Text::script('COM_PHOCASEO_RULE_PASSIVE_VOICE');
        Text::script('COM_PHOCASEO_RULE_MSG_TITLE_TOO_SHORT');
        Text::script('COM_PHOCASEO_RULE_MSG_TITLE_TOO_LONG');
        Text::script('COM_PHOCASEO_RULE_MSG_TITLE_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_TITLE_KW_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_TITLE_KW_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_TITLE_KW_START_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_TITLE_KW_START_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_DESC_TOO_SHORT');
        Text::script('COM_PHOCASEO_RULE_MSG_DESC_TOO_LONG');
        Text::script('COM_PHOCASEO_RULE_MSG_DESC_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_DESC_KW_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_DESC_KW_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_KEYS_TOO_LONG');
        Text::script('COM_PHOCASEO_RULE_MSG_KEYS_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_KEYS_COUNT_MANY');
        Text::script('COM_PHOCASEO_RULE_MSG_KEYS_COUNT_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_SLUG_KW_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_SLUG_KW_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_DENSITY_LOW');
        Text::script('COM_PHOCASEO_RULE_MSG_DENSITY_HIGH');
        Text::script('COM_PHOCASEO_RULE_MSG_DENSITY_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_SUBHEADINGS_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_SUBHEADINGS_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_H_KW_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_H_KW_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_ALTS_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_ALTS_NONE');
        Text::script('COM_PHOCASEO_RULE_MSG_ALTS_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_INTRO_KW_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_INTRO_KW_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_OUTBOUND_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_OUTBOUND_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_INTERNAL_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_INTERNAL_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_READABILITY_HARD');
        Text::script('COM_PHOCASEO_RULE_MSG_READABILITY_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_TRANSITION_LOW');
        Text::script('COM_PHOCASEO_RULE_MSG_TRANSITION_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_PASSIVE_MUCH');
        Text::script('COM_PHOCASEO_RULE_MSG_PASSIVE_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_SCHEMA_TYPE_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_SCHEMA_TYPE_GOOD');
        Text::script('COM_PHOCASEO_RULE_MSG_SCHEMA_MANDATORY_MISSING');
        Text::script('COM_PHOCASEO_RULE_MSG_SCHEMA_MANDATORY_GOOD');
        Text::script('COM_PHOCASEO_NO_MATCHING_CONTENT');
        Text::script('COM_PHOCASEO_ENTER_KEYWORD_SUGGESTIONS');
        Text::script('COM_PHOCASEO_JS_PROVIDE_CONTENT_KEYWORD');
        Text::script('COM_PHOCASEO_JS_TRANSITION_WORDS');
        Text::script('COM_PHOCASEO_JS_PASSIVE_AUX');



        // Register component namespace manually for cross-component usage
        \JLoader::registerNamespace('Phoca\\Component\\PhocaSeo', JPATH_ADMINISTRATOR . '/components/com_phocaseo/src');

        // Force load the field class to ensure it's available
        require_once JPATH_ADMINISTRATOR . '/components/com_phocaseo/src/Field/SeoSidebarField.php';

        $form->loadFile(dirname(__DIR__, 2) . '/forms/seo.xml', false);

        $itemId  = (int) ($data->id ?? 0);
        $context = $form->getName();

        if ($itemId > 0) {
            $metadata = PhocaSeoHelper::getMetadata($context, $itemId);

            if ($metadata) {
                $form->setValue('focus_keyword', null, $metadata->focus_keyword ?? '');
               // $form->setValue('cornerstone_content', null, $metadata->cornerstone_content ?? 0);
                $form->setValue('seo_score', null, $metadata->seo_score ?? 0);
                $form->setValue('canonical_url', null, $metadata->canonical_url ?? '');
               // $form->setValue('sitemap_exclude', null, $metadata->sitemap_exclude ?? 0);
            }
        }

        if ($this->params->get('display_analysis', 1)) {
            $this->loadAnalysisAssets($itemId, $context);
        }

        return true;
    }

    public function onContentAfterSave(AfterSaveEvent $event): bool
    {
        $context = $event->getContext();
        $item    = $event->getItem();
        if (!in_array($context, ['com_content.article'])) return true;

        $app = Factory::getApplication();
        $formData = $app->input->get('jform', [], 'array');
        // Check if we have SEO data to save (focus_keyword is always present if form loaded)
        if (!isset($formData['focus_keyword'])) return true;

        $this->saveMetadata($context, (int) $item->id, $formData);
        return true;
    }

    protected function saveMetadata(string $context, int $itemId, array $data): void
    {
        $db = $this->dbo;
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__phocaseo_metadata'))
            ->where($db->quoteName('context') . ' = :context')
            ->where($db->quoteName('item_id') . ' = :itemId')
            ->bind(':context', $context)
            ->bind(':itemId', $itemId, ParameterType::INTEGER);

        $db->setQuery($query);
        $id = (int) $db->loadResult();

        $now = Factory::getDate()->toSql();

        // Map Form values to DB columns
        $seoData = [
            'focus_keyword'       => $data['focus_keyword'] ?? '',
            'cornerstone_content' => (int) ($data['cornerstone_content'] ?? 0),
            'seo_score'           => (int) ($data['seo_score'] ?? 0),
            'canonical_url'       => $data['canonical_url'] ?? '',
            'sitemap_exclude'     => (int) ($data['sitemap_exclude'] ?? 0),
            'modified'            => $now,
        ];

        if ($id > 0) {
            $object = (object) array_merge(['id' => $id], $seoData);
            $db->updateObject('#__phocaseo_metadata', $object, 'id');
        } else {
            $object = (object) array_merge([
                'context' => $context,
                'item_id' => $itemId,
                'created' => $now
            ], $seoData);
            $db->insertObject('#__phocaseo_metadata', $object);
        }
    }

    protected function loadAnalysisAssets(int $itemId, string $context): void
    {
        /** @var \Joomla\CMS\Application\AdministratorApplication $app */
        $app = Factory::getApplication();
        $doc = $app->getDocument();
        $wa  = $doc->getWebAssetManager();

        HTMLHelper::_('behavior.core');
        HTMLHelper::_('behavior.formvalidator');

        if (!$wa->getRegistry()->exists('style', 'com_phocaseo.admin.item-edit')) {
            $wa->getRegistry()->addExtensionRegistryFile('com_phocaseo');
        }

        $wa->useStyle('com_phocaseo.admin.item-edit');
        $wa->useScript('com_phocaseo.admin.item-edit');

        $rules = SeoRulesHelper::getRules();
        $rulesJson = json_encode($rules);

        $script = "(function() {
    const initPhocaSeo = () => {
        // Extract Images from Joomla Data
        let articleImages = {intro: \"\", full: \"\"};
        const imagesRaw = document.getElementById('jform_images')?.value;
        if (imagesRaw) {
            try {
                const imgObj = JSON.parse(imagesRaw);
                if (imgObj.image_intro) articleImages.intro = imgObj.image_intro;
                if (imgObj.image_fulltext) articleImages.full = imgObj.image_fulltext;
            } catch(e) {}
        }

        // Hidden metadata for AJAX - use adminForm or body as fallback
        if (!document.getElementById('jform_item_id')) {
            const target = document.getElementById('adminForm') || document.body;
            const hidId = document.createElement('input');
            hidId.type = 'hidden'; hidId.id = 'jform_item_id'; hidId.value = '{$itemId}';
            target.appendChild(hidId);
            const hidCtx = document.createElement('input');
            hidCtx.type = 'hidden'; hidCtx.id = 'jform_context'; hidCtx.value = '{$context}';
            target.appendChild(hidCtx);
        }

        if (typeof window.PhocaSeoAdmin !== 'undefined') {
            window.PhocaSeoAdmin.init({$rulesJson}, articleImages);
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPhocaSeo);
    else setTimeout(initPhocaSeo, 200);
})();";


        $wa->addInlineScript($script, [], ['type' => 'text/javascript'], ['core']);
        //$doc->addScriptDeclaration($script);
    }
}
