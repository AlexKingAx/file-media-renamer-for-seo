<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**========================================================
 * Claude (Anthropic) provider implementation.
 **========================================================*/
class FMRSEO_Claude_Provider implements FMRSEO_AI_Provider_Interface
{
    use FMRSEO_AI_Provider_Helpers;

    /**
     * Anthropic Messages API endpoint.
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * Anthropic API version header value.
     */
    const API_VERSION = '2023-06-01';

    /**
     * Maximum characters read from plain-text documents to keep the prompt small.
     */
    const MAX_TEXT_CHARS = 20000;

    /**
     * Generates a raw filename proposal for an attachment.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $settings      AI settings.
     *
     * @return string|WP_Error
     */
    public function generate_name_for_attachment($attachment_id, $settings)
    {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
            return new WP_Error('fmrseo_ai_invalid_attachment', esc_html__('Invalid attachment.', 'file-media-renamer-for-seo'));
        }

        $api_key = isset($settings['api_key']) ? trim((string) $settings['api_key']) : '';
        if (empty($api_key)) {
            return new WP_Error('fmrseo_ai_missing_key', esc_html__('Claude API key is missing.', 'file-media-renamer-for-seo'));
        }

        $file_path = get_attached_file($attachment_id);
        if (empty($file_path) || !file_exists($file_path)) {
            return new WP_Error('fmrseo_ai_file_not_found', esc_html__('Attachment file not found.', 'file-media-renamer-for-seo'));
        }

        $mime_type = (string) get_post_mime_type($attachment_id);
        if (empty($mime_type)) {
            $check = wp_check_filetype($file_path);
            if (!empty($check['type'])) {
                $mime_type = (string) $check['type'];
            }
        }

        $extension = strtolower((string) pathinfo($file_path, PATHINFO_EXTENSION));
        if ($this->should_skip_extension($extension)) {
            return new WP_Error('fmrseo_ai_skipped_extension', esc_html__('This file type is skipped by AI rename.', 'file-media-renamer-for-seo'));
        }

        $model = isset($settings['model']) ? trim((string) $settings['model']) : '';
        if (empty($model)) {
            $model = 'claude-opus-4-8';
        }

        $website_info = isset($settings['website_info']) ? (string) $settings['website_info'] : '';
        $brand = isset($settings['brand']) ? (string) $settings['brand'] : '';

        if (strpos($mime_type, 'image/') === 0) {
            $prompt = $this->build_image_prompt($website_info, $brand);
            return $this->generate_from_image($api_key, $model, $file_path, $mime_type, $prompt);
        }

        if ('application/pdf' === $mime_type || 'pdf' === $extension) {
            $prompt = $this->build_document_prompt($website_info, $brand);
            return $this->generate_from_pdf($api_key, $model, $file_path, $prompt);
        }

        if ($this->is_supported_text_type($mime_type, $extension)) {
            $prompt = $this->build_document_prompt($website_info, $brand);
            return $this->generate_from_text($api_key, $model, $file_path, $prompt);
        }

        return new WP_Error('fmrseo_ai_skipped_file_type', esc_html__('AI rename skipped this unsupported file type.', 'file-media-renamer-for-seo'));
    }

    /**
     * Builds image prompt.
     *
     * @param string $website_info Website info.
     * @param string $brand        Brand.
     * @return string
     */
    private function build_image_prompt($website_info, $brand)
    {
        return sprintf(
            "Genera un nome file SEO in italiano per questa immagine. Info sito: '%s'. Brand da includere: '%s'. Regole: tutto minuscolo, parole separate da trattini, breve, niente estensione, nessun testo extra o virgolette. Rispondi solo con il nome.",
            $website_info,
            $brand
        );
    }

    /**
     * Builds document prompt.
     *
     * @param string $website_info Website info.
     * @param string $brand        Brand.
     * @return string
     */
    private function build_document_prompt($website_info, $brand)
    {
        return sprintf(
            "Genera un nome file SEO in italiano per questo documento. Basati sul contenuto del file se disponibile. Info sito: '%s'. Brand da includere: '%s'. Regole: tutto minuscolo, parole separate da trattini, breve, niente estensione, nessun testo extra o virgolette. Rispondi solo con il nome.",
            $website_info,
            $brand
        );
    }

    /**
     * Generates output from an image.
     *
     * @param string $api_key   API key.
     * @param string $model     Claude model.
     * @param string $file_path File path.
     * @param string $mime_type Mime type.
     * @param string $prompt    Prompt.
     * @return string|WP_Error
     */
    private function generate_from_image($api_key, $model, $file_path, $mime_type, $prompt)
    {
        $file_data = $this->read_local_file_contents($file_path);
        if (is_wp_error($file_data)) {
            return $file_data;
        }

        $content_items = array(
            array(
                'type' => 'text',
                'text' => $prompt,
            ),
            array(
                'type' => 'image',
                'source' => array(
                    'type' => 'base64',
                    'media_type' => $mime_type,
                    'data' => base64_encode($file_data),
                ),
            ),
        );

        return $this->send_messages_request($api_key, $model, $content_items);
    }

    /**
     * Generates output from a PDF document.
     *
     * @param string $api_key   API key.
     * @param string $model     Claude model.
     * @param string $file_path File path.
     * @param string $prompt    Prompt.
     * @return string|WP_Error
     */
    private function generate_from_pdf($api_key, $model, $file_path, $prompt)
    {
        $file_data = $this->read_local_file_contents($file_path);
        if (is_wp_error($file_data)) {
            return $file_data;
        }

        $content_items = array(
            array(
                'type' => 'text',
                'text' => $prompt,
            ),
            array(
                'type' => 'document',
                'source' => array(
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => base64_encode($file_data),
                ),
            ),
        );

        return $this->send_messages_request($api_key, $model, $content_items);
    }

    /**
     * Generates output from a plain-text document.
     *
     * @param string $api_key   API key.
     * @param string $model     Claude model.
     * @param string $file_path File path.
     * @param string $prompt    Prompt.
     * @return string|WP_Error
     */
    private function generate_from_text($api_key, $model, $file_path, $prompt)
    {
        $file_data = $this->read_local_file_contents($file_path);
        if (is_wp_error($file_data)) {
            return $file_data;
        }

        $text_content = mb_substr((string) $file_data, 0, self::MAX_TEXT_CHARS);

        $content_items = array(
            array(
                'type' => 'text',
                'text' => $prompt,
            ),
            array(
                'type' => 'text',
                'text' => $text_content,
            ),
        );

        return $this->send_messages_request($api_key, $model, $content_items);
    }

    /**
     * Sends a request to the Messages API.
     *
     * @param string $api_key       API key.
     * @param string $model         Claude model.
     * @param array  $content_items Content list.
     * @return string|WP_Error
     */
    private function send_messages_request($api_key, $model, $content_items)
    {
        $response = wp_remote_post(
            self::API_ENDPOINT,
            array(
                'headers' => array(
                    'x-api-key' => $api_key,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 60,
                'body' => wp_json_encode(
                    array(
                        'model' => $model,
                        'max_tokens' => 1024,
                        'messages' => array(
                            array(
                                'role' => 'user',
                                'content' => $content_items,
                            ),
                        ),
                    )
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('fmrseo_ai_request_failed', esc_html__('Claude request failed. Please try again.', 'file-media-renamer-for-seo'));
        }

        return $this->extract_output_from_response($response);
    }

    /**
     * Extracts the final output text from a Messages API response.
     *
     * @param array $response Raw WP HTTP response.
     * @return string|WP_Error
     */
    private function extract_output_from_response($response)
    {
        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            $default_message = esc_html__('Claude returned an error. Please check your API key, model, and usage limits.', 'file-media-renamer-for-seo');
            return new WP_Error('fmrseo_ai_api_error', $this->extract_json_error_message($body, array('error', 'message'), $default_message));
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return new WP_Error('fmrseo_ai_invalid_response', esc_html__('Invalid Claude response format.', 'file-media-renamer-for-seo'));
        }

        if (isset($payload['stop_reason']) && 'refusal' === $payload['stop_reason']) {
            return new WP_Error('fmrseo_ai_refusal', esc_html__('Claude declined to generate a filename for this file.', 'file-media-renamer-for-seo'));
        }

        if (!empty($payload['content']) && is_array($payload['content'])) {
            $parts = array();

            foreach ($payload['content'] as $content_item) {
                if (!empty($content_item['type']) && 'text' === $content_item['type'] && !empty($content_item['text']) && is_string($content_item['text'])) {
                    $parts[] = trim($content_item['text']);
                }
            }

            if (!empty($parts)) {
                $full_text = trim(implode(' ', $parts));
                if (!empty($full_text)) {
                    return $full_text;
                }
            }
        }

        $stop_reason = isset($payload['stop_reason']) && is_string($payload['stop_reason']) ? $payload['stop_reason'] : '';

        return new WP_Error('fmrseo_ai_empty_response', $this->describe_empty_response($stop_reason));
    }

    /**
     * Builds a descriptive error message for an empty Claude response.
     *
     * @param string $stop_reason Claude's stop_reason value, if any.
     * @return string
     */
    private function describe_empty_response($stop_reason)
    {
        if ('max_tokens' === $stop_reason) {
            return esc_html__('Claude used all available output tokens for internal reasoning and produced no filename. Try increasing the model output limit or use a different model.', 'file-media-renamer-for-seo');
        }

        if (!empty($stop_reason)) {
            return sprintf(
                /* translators: %s is Claude's stop_reason value. */
                esc_html__('Claude did not return a filename (stop reason: %s).', 'file-media-renamer-for-seo'),
                sanitize_text_field($stop_reason)
            );
        }

        return esc_html__('Claude did not return a filename.', 'file-media-renamer-for-seo');
    }

    /**
     * Checks if the extension should be skipped a priori.
     *
     * @param string $extension File extension.
     * @return bool
     */
    private function should_skip_extension($extension)
    {
        $blocked_extensions = array(
            'woff',
            'woff2',
            'ttf',
            'otf',
            'eot',
            'fon',
            'pfb',
            'pfm',
        );

        return in_array($extension, $blocked_extensions, true);
    }

    /**
     * Checks whether the file is a plain-text type Claude can read directly.
     *
     * @param string $mime_type Mime type.
     * @param string $extension File extension.
     * @return bool
     */
    private function is_supported_text_type($mime_type, $extension)
    {
        $text_mimes = array(
            'text/plain',
            'text/csv',
            'text/markdown',
            'application/json',
            'application/xml',
            'text/xml',
            'application/rtf',
        );

        if (in_array($mime_type, $text_mimes, true)) {
            return true;
        }

        $text_extensions = array(
            'txt',
            'csv',
            'json',
            'xml',
            'md',
            'rtf',
        );

        return in_array($extension, $text_extensions, true);
    }
}
