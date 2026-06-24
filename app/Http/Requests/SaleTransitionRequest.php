<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaleTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:4096'],
            'messages' => ['nullable', 'array', 'min:1', 'max:5'],
            'messages.*.content' => ['required_with:messages', 'string', 'max:4096'],
            'messages.*.delay_seconds' => ['nullable', 'integer', 'min:0', 'max:300'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $messages = $this->input('messages');

        if (is_array($messages) && ! empty($messages)) {
            // When messages[] is used, ensure `message` is set for backward-compat validators
            $this->merge([
                'message' => strip_tags($messages[0]['content'] ?? ''),
            ]);
        } else {
            $this->merge([
                'message' => strip_tags($this->input('message', '')),
            ]);
        }
    }

    /**
     * Returns the list of message bubbles to send in sequence.
     *
     * @return list<array{content: string, delay_seconds: int}>
     */
    public function messageBubbles(): array
    {
        $messages = $this->input('messages');

        if (is_array($messages) && ! empty($messages)) {
            return array_map(fn ($m) => [
                'content' => strip_tags($m['content'] ?? ''),
                'delay_seconds' => (int) ($m['delay_seconds'] ?? 0),
            ], $messages);
        }

        $message = $this->input('message', '');

        return [['content' => strip_tags($message), 'delay_seconds' => 0]];
    }
}
