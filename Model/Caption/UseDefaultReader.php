<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Model\Caption;

/**
 * Reads the "Use Default Value" flags out of a submitted admin form.
 *
 * TWO MECHANISMS WRITE THE SAME KEY, and both must be accepted. The rendered checkbox in
 * `ui/form/element/helper/service` carries `name="use_default[<index>]"`, which a plain browser
 * submit sends as the string `on`; separately `abstract.js::toggleUseDefault` writes
 * `data.use_default.<index>` as the number 0 or 1. Reading only one of them works right up until the
 * other path is taken, and then silently stops honouring the checkbox.
 */
class UseDefaultReader
{
    /**
     * Submission key the checkbox posts under.
     */
    private const KEY = 'use_default';

    /**
     * Check whether the merchant asked this field to fall back to its default.
     *
     * @param mixed[] $submission The posted form data.
     * @param string $field The field's UI index, e.g. `label`.
     * @return bool
     */
    public function isDefaultRequested(array $submission, string $field): bool
    {
        $flags = $submission[self::KEY] ?? null;

        if (!is_array($flags) || !array_key_exists($field, $flags)) {
            return false;
        }

        return $this->isTruthy($flags[$field]);
    }

    /**
     * Reduce one flag value to a boolean.
     *
     * Deliberately not a loose cast: `'0'` is truthy under (bool) in some paths and is precisely the
     * value the JS writes for "unchecked", so a cast here would invert the feature.
     *
     * @param mixed $value
     * @return bool
     */
    private function isTruthy(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 'on';
    }
}
