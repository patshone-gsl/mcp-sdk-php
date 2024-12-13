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
 * Filename: Types/ClientRequest.php
 */

declare(strict_types=1);

namespace Mcp\Types;

/**
 * Union type for client requests:
 * type ClientRequest =
 *   | InitializeRequest
 *   | PingRequest
 *   | ListResourcesRequest
 *   | ReadResourceRequest
 *   | SubscribeRequest
 *   | UnsubscribeRequest
 *   | ListPromptsRequest
 *   | GetPromptRequest
 *   | ListToolsRequest
 *   | CallToolRequest
 *   | SetLevelRequest
 *   | CompleteRequest
 *
 * This acts as a root model for that union.
 */
class ClientRequest implements McpModel {
    use ExtraFieldsTrait;

    private Request $request;

    public function __construct(Request $request) {
        if (!(
            $request instanceof InitializeRequest ||
            $request instanceof PingRequest ||
            $request instanceof ListResourcesRequest ||
            $request instanceof ReadResourceRequest ||
            $request instanceof SubscribeRequest ||
            $request instanceof UnsubscribeRequest ||
            $request instanceof ListPromptsRequest ||
            $request instanceof GetPromptRequest ||
            $request instanceof ListToolsRequest ||
            $request instanceof CallToolRequest ||
            $request instanceof SetLevelRequest ||
            $request instanceof CompleteRequest
        )) {
            throw new \InvalidArgumentException('Invalid client request type');
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