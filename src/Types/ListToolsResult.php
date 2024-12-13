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
 * Filename: Types/ListToolsResult.php
 */

declare(strict_types=1);

namespace Mcp\Types;

class ListToolsResult extends PaginatedResult {
    /**
     * @param Tool[] $tools
     */
    public function __construct(
        public readonly array $tools,
        ?string $nextCursor = null,
        ?Meta $_meta = null,
    ) {
        parent::__construct($nextCursor, $_meta);
    }

    public function validate(): void {
        parent::validate();
        foreach ($this->tools as $tool) {
            if (!$tool instanceof Tool) {
                throw new \InvalidArgumentException('Tools must be instances of Tool');
            }
            $tool->validate();
        }
    }

    public function jsonSerialize(): mixed {
        $data = parent::jsonSerialize();
        $data['tools'] = $this->tools;
        return $data;
    }
}