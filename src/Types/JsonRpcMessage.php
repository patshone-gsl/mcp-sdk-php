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
 * Filename: Types/JsonRpcMessage.php
 */

declare(strict_types=1);

namespace Mcp\Types;

class JsonRpcMessage implements McpModel {
    use ExtraFieldsTrait;

    public readonly JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError $message;

    public function __construct(
        JSONRPCRequest|JSONRPCNotification|JSONRPCResponse|JSONRPCError $message = null,
        ?string $jsonrpc = null,
        ?RequestId $id = null,
        ?string $method = null,
        mixed $params = null,
        mixed $result = null,
        mixed $error = null,
    ) {
        if ($message !== null) {
            // If a pre-constructed message is provided, just use it
            $this->message = $message;
            return;
        }

        // If no pre-constructed message is given, we must infer the message type
        $jsonrpc = $jsonrpc ?? '2.0';

        // Determine message type by provided parameters:
        // 1) JSONRPCRequest: has $id, $method, no $result, no $error
        // 2) JSONRPCNotification: no $id, has $method, no $error, no $result
        // 3) JSONRPCResponse: has $id, has $result, no $error
        // 4) JSONRPCError: has $id, has $error, no $result

        if ($id !== null && $method !== null && $result === null && $error === null) {
            // Looks like a Request
            if (!($params === null || $params instanceof RequestParams)) {
                throw new \InvalidArgumentException("For a request, params must be RequestParams or null.");
            }
            $this->message = new JSONRPCRequest(
                jsonrpc: $jsonrpc,
                id: $id,
                params: $params,
                method: $method
            );
        } elseif ($method !== null && $id === null && $error === null && $result === null) {
            // Notification
            if (!($params === null || $params instanceof NotificationParams)) {
                throw new \InvalidArgumentException("For a notification, params must be NotificationParams or null.");
            }
            $this->message = new JSONRPCNotification(
                jsonrpc: $jsonrpc,
                params: $params,
                method: $method
            );
        } elseif ($id !== null && $result !== null && $error === null) {
            // Response
            if (!$result instanceof Result) {
                throw new \InvalidArgumentException("For a response, result must be an instance of Result.");
            }
            $this->message = new JSONRPCResponse(
                jsonrpc: $jsonrpc,
                id: $id,
                result: $result
            );
        } elseif ($id !== null && $error !== null && $result === null) {
            // Error
            if (!$error instanceof JsonRpcErrorObject) {
                throw new \InvalidArgumentException("For an error, error must be a JsonRpcErrorObject instance.");
            }
            $this->message = new JSONRPCError(
                jsonrpc: $jsonrpc,
                id: $id,
                error: $error
            );
        } else {
            // Could not determine a valid message type
            throw new \InvalidArgumentException("Insufficient or conflicting parameters to construct a JSON-RPC message.");
        }
    }

    public function validate(): void {
        $this->message->validate();
    }

    public function jsonSerialize(): mixed {
        $data = $this->message->jsonSerialize();
        return array_merge((array)$data, $this->extraFields);
    }
}