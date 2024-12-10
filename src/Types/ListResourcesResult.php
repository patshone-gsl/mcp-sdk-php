<?php

/**
 * Model Context Protocol SDK for PHP
 *
 * (c) 2024 Logiscape LLC <https://logiscape.com>
 *
 * Based on the Python SDK for the Model Context Protocol
 * https://github.com/modelcontextprotocol/python-sdk
 *
 * PHP conversion developed by:
 * - Josh Abbott
 * - Claude 3.5 Sonnet (Anthropic AI model)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    logiscape/mcp-sdk-php
 * @author     Josh Abbott <https://joshabbott.com>
 * @copyright  Logiscape LLC
 * @license    MIT License
 * @link       https://github.com/logiscape/mcp-sdk-php
 *
 * Filename: Types/ListResourcesResult.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Result of listing available resources
 */
class ListResourcesResult extends PaginatedResult {
    /**
     * @param Resource[] $resources
     */
    public function __construct(
        public readonly array $resources,
        ?string $nextCursor = null,
        ?array $meta = null,
    ) {
        parent::__construct($nextCursor, $meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->resources as $resource) {
            if (!$resource instanceof Resource) {
                throw new \InvalidArgumentException('Resources must be instances of Resource');
            }
            $resource->validate();
        }
    }
}