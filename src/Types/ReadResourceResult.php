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
 * Filename: Types/ReadResourceResult.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Result of reading a resource
 */
class ReadResourceResult extends Result {
    /**
     * @param (TextResourceContents|BlobResourceContents)[] $contents
     */
    public function __construct(
        public readonly array $contents,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->contents as $content) {
            if (!($content instanceof TextResourceContents || $content instanceof BlobResourceContents)) {
                throw new \InvalidArgumentException('Resource contents must be instances of TextResourceContents or BlobResourceContents');
            }
            $content->validate();
        }
    }
}