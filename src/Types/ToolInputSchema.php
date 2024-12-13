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
 * Filename: Types/ToolInputSchema.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Represents the inputSchema for Tool:
 * {
 *   type: "object",
 *   properties?: { [key: string]: object },
 *   required?: string[]
 * }
 */
class ToolInputSchema implements McpModel {
    use ExtraFieldsTrait;

    /**
     * @param string[]|null $required
     */
    public function __construct(
        public readonly ToolInputProperties $properties = new ToolInputProperties(),
        public ?array $required = null,
    ) {}

    public function validate(): void {
        // type is always "object", we enforce this in the Tool class itself.
        if ($this->required !== null) {
            foreach ($this->required as $r) {
                if (!is_string($r) || $r === '') {
                    throw new \InvalidArgumentException('Required field names must be non-empty strings');
                }
            }
        }
        $this->properties->validate();
    }

    public function jsonSerialize(): mixed {
        $data = [
            'type' => 'object',
        ];
        if ($this->required !== null) {
            $data['required'] = $this->required;
        }
        // properties is optional, but if not empty, we include it
        if (!empty($this->properties)) {
            $data['properties'] = $this->properties;
        }
        return array_merge($data, $this->extraFields);
    }
}