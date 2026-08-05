<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Ui\DataProvider\Modifier;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;
use Muon\Core\Api\CaptionStorageInterface;
use Muon\Core\Model\Caption\CaptionScope;

/**
 * Turns a form into a store-view-scoped one: captions editable, structure locked.
 *
 * IN DEFAULT SCOPE THIS IS A NO-OP. Every existing form keeps its exact behaviour until a merchant
 * actually switches scope, which is what makes the feature additive.
 *
 * HOW THE CHECKBOX'S INITIAL STATE IS SET — this is not obvious and is easy to get backwards.
 * `Magento_Ui/js/form/element/abstract::setInitialValue` runs `this.isUseDefault(this.disabled())`,
 * so the metadata flag `disabled` is what decides whether "Use Default Value" starts ticked. The
 * field is therefore marked disabled when the store has NO override — the merchant is currently
 * using the default — and enabled when it has one. Magento's own product Eav modifier sets it from
 * the same inverted condition; matching it is why the flag reads backwards at first glance.
 *
 * DISABLING A FIELD IS A UX GUARD, NOT AUTHORISATION. A crafted POST can still carry every
 * structural field. The Save controller has to ignore them in store scope; nothing here can.
 *
 * IT RETURNS A PARTIAL TREE, IT DOES NOT EDIT AN EXISTING ONE. For a form whose fields are declared
 * in XML, `AbstractDataProvider::getMeta()` returns an EMPTY array — the fields never pass through
 * here. `UiComponentFactory::mergeMetadata()` recursively merges whatever the data provider returns
 * onto the XML-derived bundle, so the way to reach a field is to emit the path for it and let the
 * merge land it. An earlier version skipped paths that were absent from the incoming meta, which
 * meant it silently did nothing at all: the checkbox never rendered and nothing was ever locked.
 *
 * A CONSEQUENCE WORTH KNOWING: because the merge overwrites, this class must not write a `notice`.
 * Several of the fields it locks carry a security warning there ("Never use this to hide
 * confidential content"), and there is no incoming meta to read them back from, so a scope hint
 * would delete them with no way to restore. The disabled state plus the store switcher is the
 * signal instead.
 *
 * EVERY CONFIGURED PATH MUST NAME A FIELD THE FORM DECLARES. Emitting one that does not creates a
 * component with no formElement, which the renderer rejects with a message naming neither the field
 * nor this class.
 *
 * SINGLE-CAPTION ENTITIES ONLY. Every configured caption field shares one override flag, because the
 * entities this serves carry exactly one caption. A form with per-row captions (a dynamicRows grid)
 * needs its own handling — the rendered checkbox posts under `use_default[<index>]`, and every row
 * of a grid shares one index.
 */
class StoreScopeFields implements ModifierInterface
{
    /**
     * Magento's shared template for the "Use Default Value" checkbox.
     */
    private const SERVICE_TEMPLATE = 'ui/form/element/helper/service';

    /**
     * @param \Magento\Framework\Stdlib\ArrayManager $arrayManager
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Muon\Core\Model\Caption\CaptionScope $scope
     * @param \Muon\Core\Api\CaptionStorageInterface $captionStorage
     * @param string $entityRequestField Request parameter naming the edited entity, e.g. `item_id`.
     * @param string[] $captionFields Meta paths of the caption fields, e.g. `general/children/label`.
     * @param string[] $structuralFields Meta paths of the fields to lock in store scope.
     */
    public function __construct(
        private readonly ArrayManager $arrayManager,
        private readonly RequestInterface $request,
        private readonly CaptionScope $scope,
        private readonly CaptionStorageInterface $captionStorage,
        private readonly string $entityRequestField,
        private readonly array $captionFields = [],
        private readonly array $structuralFields = []
    ) {
    }

    /**
     * Leave the form's data alone; the DataProvider owns seeding the scoped value.
     *
     * Types are restated rather than inherited: ModifierInterface declares a bare `array`, so
     * @inheritDoc here leaves PHPStan level 8 without a value type.
     *
     * @param mixed[] $data
     * @return mixed[]
     */
    public function modifyData(array $data): array
    {
        return $data;
    }

    /**
     * Scope the form's fields when a store view is selected.
     *
     * @param mixed[] $meta
     * @return mixed[]
     */
    public function modifyMeta(array $meta): array
    {
        if ($this->scope->isDefaultScope()) {
            return $meta;
        }

        $hasOverride = $this->hasOverride();

        foreach ($this->captionFields as $path) {
            $meta = $this->writeConfig($meta, $path, [
                'service' => ['template' => self::SERVICE_TEMPLATE],
                'scopeLabel' => (string) __('[STORE VIEW]'),
                // abstract.js reads this as the checkbox's initial state: ticked when the store has
                // no override, because the merchant is currently on the default.
                'disabled' => !$hasOverride,
            ]);
        }

        foreach ($this->structuralFields as $path) {
            // `disabled` only — never `notice`. See the class docblock.
            $meta = $this->writeConfig($meta, $path, ['disabled' => true]);
        }

        return $meta;
    }

    /**
     * Emit config for one field, creating the path when it is not already present.
     *
     * ArrayManager::set builds the intermediate nodes, which is required here: the incoming meta is
     * empty for an XML-declared form, and this return value is merged onto the XML bundle by
     * UiComponentFactory.
     *
     * @param mixed[] $meta
     * @param string $path
     * @param mixed[] $config
     * @return mixed[]
     */
    private function writeConfig(array $meta, string $path, array $config): array
    {
        $configPath = $path . '/arguments/data/config';
        $existing = $this->arrayManager->get($configPath, $meta);

        return $this->arrayManager->set(
            $configPath,
            $meta,
            is_array($existing) ? array_replace($existing, $config) : $config
        );
    }

    /**
     * Check whether the edited entity already has a caption for the current store view.
     *
     * @return bool
     */
    private function hasOverride(): bool
    {
        $entityId = (int) $this->request->getParam($this->entityRequestField);

        if ($entityId <= 0) {
            return false;
        }

        $captions = $this->captionStorage->loadForStore([$entityId], $this->scope->getStoreId());

        return array_key_exists($entityId, $captions);
    }
}
