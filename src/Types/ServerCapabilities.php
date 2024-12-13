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
 * Filename: Types/ServerCapabilities.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Server capabilities
 * According to the schema:
 * ServerCapabilities {
 *   experimental?: { ... },
 *   logging?: object,
 *   prompts?: { listChanged?: boolean },
 *   resources?: { subscribe?: boolean, listChanged?: boolean },
 *   tools?: { listChanged?: boolean },
 *   [key: string]: unknown
 * }
 */
class ServerCapabilities extends Capabilities {
    public function __construct(
        public ?ServerLoggingCapability $logging = null,
        public ?ServerPromptsCapability $prompts = null,
        public ?ServerResourcesCapability $resources = null,
        public ?ServerToolsCapability $tools = null,
        ?ExperimentalCapabilities $experimental = null,
    ) {
        parent::__construct($experimental);
    }

    public function validate(): void {
        parent::validate();
        if ($this->prompts !== null) {
            $this->prompts->validate();
        }
        if ($this->resources !== null) {
            $this->resources->validate();
        }
        if ($this->tools !== null) {
            $this->tools->validate();
        }
        if ($this->logging !== null) {
            $this->logging->validate();
        }
    }

    public function jsonSerialize(): mixed {
        $data = parent::jsonSerialize();
        if ($this->logging !== null) {
            $data['logging'] = $this->logging;
        }
        if ($this->prompts !== null) {
            $data['prompts'] = $this->prompts;
        }
        if ($this->resources !== null) {
            $data['resources'] = $this->resources;
        }
        if ($this->tools !== null) {
            $data['tools'] = $this->tools;
        }
        return $data;
    }
}