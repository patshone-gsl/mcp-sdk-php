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
 * Filename: Types/ServerRequest.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Union type for server requests:
 * type ServerRequest =
 *   | PingRequest
 *   | CreateMessageRequest
 *   | ListRootsRequest
 *
 * Represented as a root model holding one valid Request variant.
 */
class ServerRequest implements McpModel {
    use ExtraFieldsTrait;

    private Request $request;

    public function __construct(Request $request) {
        if (!(
            $request instanceof PingRequest ||
            $request instanceof CreateMessageRequest ||
            $request instanceof ListRootsRequest
        )) {
            throw new \InvalidArgumentException('Invalid server request type');
        }
        $this->request = $request;
    }

    public function validate(): void {
        $this->request->validate();
    }

    public function getRequest(): Request {
        return $this->request;
    }

    public function jsonSerialize(): mixed {
        $data = $this->request->jsonSerialize();
        return array_merge((array)$data, $this->extraFields);
    }
}