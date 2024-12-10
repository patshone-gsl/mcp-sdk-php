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
 * Filename: Types/CallToolResult.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Result of a tool call
 */
class CallToolResult extends Result {
    /**
     * @param (TextContent|ImageContent|EmbeddedResource)[] $content
     */
    public function __construct(
        public readonly array $content,
        public ?bool $isError = false,
        ?array $meta = null,
    ) {
        parent::__construct($meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->content as $item) {
            if (!($item instanceof TextContent || $item instanceof ImageContent || $item instanceof EmbeddedResource)) {
                throw new \InvalidArgumentException('Tool call content must be instances of TextContent, ImageContent, or EmbeddedResource');
            }
            $item->validate();
        }
    }
}