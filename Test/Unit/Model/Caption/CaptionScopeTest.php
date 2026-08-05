<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\Core\Test\Unit\Model\Caption;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Muon\Core\Model\Caption\CaptionScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CaptionScopeTest extends TestCase
{
    /**
     * Store ids this fake estate recognises.
     */
    private const KNOWN_STORES = [1, 2, 3];

    /**
     * @param mixed $storeParam
     * @return \Muon\Core\Model\Caption\CaptionScope
     */
    private function scope(mixed $storeParam): CaptionScope
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($storeParam);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            function (int|string|null $storeId): StoreInterface {
                if (!in_array((int) $storeId, self::KNOWN_STORES, true)) {
                    throw new NoSuchEntityException(__('No such store.'));
                }

                return $this->createStub(StoreInterface::class);
            }
        );

        return new CaptionScope($request, $storeManager);
    }

    /**
     * @param mixed $param
     * @param int $expected
     * @return void
     */
    #[DataProvider('storeParamProvider')]
    public function testStoreParamResolvesToAScopeOrFallsBackToDefault(mixed $param, int $expected): void
    {
        self::assertSame($expected, $this->scope($param)->getStoreId());
    }

    /**
     * @return array<string,array{mixed,int}>
     */
    public static function storeParamProvider(): array
    {
        return [
            'absent' => [null, 0],
            'empty string' => ['', 0],
            'non numeric' => ['abc', 0],
            // Store 0 is the admin store and DOES exist as a row, so an unguarded existence check
            // would accept it and make an unscoped screen look scoped.
            'admin store zero' => ['0', 0],
            'negative' => ['-4', 0],
            // A stale bookmark must not 500; it degrades to showing default values.
            'unknown store' => ['99999', 0],
            'valid store as string' => ['2', 2],
            'valid store as int' => [3, 3],
        ];
    }

    public function testDefaultScopeIsReportedForAnAbsentParameter(): void
    {
        self::assertTrue($this->scope(null)->isDefaultScope());
    }

    public function testDefaultScopeIsNotReportedForARealStore(): void
    {
        self::assertFalse($this->scope('2')->isDefaultScope());
    }
}
