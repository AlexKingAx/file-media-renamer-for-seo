<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**========================================================
 * Gemini (Google) provider implementation.
 **========================================================*/
class FMRSEO_Gemini_Provider implements FMRSEO_AI_Provider_Interface
{
    use FMRSEO_AI_Provider_Helpers;

    /**
     * Gemini generateContent API base endpoint.
     */
    const API_ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

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
            return new WP_Error('fmrseo_ai_missing_key', esc_html__('Gemini API key is missing.', 'file-media-renamer-for-seo'));
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
            $model = 'gemini-3.6-flash';
        }

        $website_info = isset($settings['website_info']) ? (string) $settings['website_info'] : '';
        $brand = isset($settings['brand']) ? (string) $settings['brand'] : '';

        if (strpos($mime_type, 'image/') === 0) {
            $prompt = $this->build_image_prompt($website_info, $brand);
            return $this->generate_from_inline_data($api_key, $model, $file_path, $mime_type, $prompt);
        }

        if ('application/pdf' === $mime_type || 'pdf' === $extension) {
            $prompt = $this->build_document_prompt($website_info, $brand);
            return $this->generate_from_inline_data($api_key, $model, $file_path, 'application/pdf', $prompt);
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
     * Generates output from an image or PDF using inline_data.
     *
     * @param string $api_key   API key.
     * @param string $model     Gemini model.
     * @param string $file_path File path.
     * @param string $mime_type Mime type.
     * @param string $prompt    Prompt.
     * @return string|WP_Error
     */
    private function generate_from_inline_data($api_key, $model, $file_path, $mime_type, $prompt)
    {
        $file_data = $this->read_local_file_contents($file_path);
        if (is_wp_error($file_data)) {
            return $file_data;
        }

        $parts = array(
            array('text' => $prompt),
            array(
                'inline_data' => array(
                    'mime_type' => $mime_type,
                    'data' => base64_encode($file_data),
                ),
            ),
        );

        return $this->send_generate_content_request($api_key, $model, $parts);
    }

    /**
     * Generates output from a plain-text document.
     *
     * @param string $api_key   API key.
     * @param string $model     Gemini model.
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

        $parts = array(
            array('text' => $prompt),
            array('text' => $text_content),
        );

        return $this->send_generate_content_request($api_key, $model, $parts);
    }

    /**
     * Sends a request to the Gemini generateContent API.
     *
     * @param string $api_key API key.
     * @param string $model   Gemini model.
     * @param array  $parts   Content parts list.
     * @return string|WP_Error
     */
    private function send_generate_content_request($api_key, $model, $parts)
    {
        $endpoint = self::API_ENDPOINT_BASE . rawurlencode($model) . ':generateContent';

        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'x-goog-api-key' => $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 60,
                'body' => wp_json_encode(
                    array(
                        'contents' => array(
                            array(
                                'role' => 'user',
                                'parts' => $parts,
                            ),
                        ),
                        'generationConfig' => array(
                            'maxOutputTokens' => 1024,
                        ),
                    )
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('fmrseo_ai_request_failed', esc_html__('Gemini request failed. Please try again.', 'file-media-renamer-for-seo'));
        }

        return $this->extract_output_from_response($response);
    }

    /**
     * Extracts the final output text from a generateContent API response.
     *
     * @param array $response Raw WP HTTP response.
     * @return string|WP_Error
     */
    private function extract_output_from_response($response)
    {
        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            $default_message = esc_html__('Gemini returned an error. Please check your API key, model, and usage limits.', 'file-media-renamer-for-seo');
            return new WP_Error('fmrseo_ai_api_error', $this->extract_json_error_message($body, array('error', 'message'), $default_message));
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return new WP_Error('fmrseo_ai_invalid_response', esc_html__('Invalid Gemini response format.', 'file-media-renamer-for-seo'));
        }

        if (empty($payload['candidates']) || !is_array($payload['candidates'])) {
            $block_reason = isset($payload['promptFeedback']['blockReason']) && is_string($payload['promptFeedback']['blockReason'])
                ? $payload['promptFeedback']['blockReason']
                : '';

            if (!empty($block_reason)) {
                return new WP_Error(
                    'fmrseo_ai_refusal',
                    sprintf(
                        /* translators: %s is Gemini's block reason code. */
                        esc_html__('Gemini declined to generate a filename for this file (block reason: %s).', 'file-media-renamer-for-seo'),
                        sanitize_text_field($block_reason)
                    )
                );
            }

            return new WP_Error('fmrseo_ai_empty_response', esc_html__('Gemini did not return any candidate response.', 'file-media-renamer-for-seo'));
        }

        $parts = array();
        $finish_reason = '';

        foreach ($payload['candidates'] as $candidate) {
            if (empty($finish_reason) && !empty($candidate['finishReason']) && is_string($candidate['finishReason'])) {
                $finish_reason = $candidate['finishReason'];
            }

            if (empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                continue;
            }

            foreach ($candidate['content']['parts'] as $part) {
                if (!empty($part['text']) && is_string($part['text'])) {
                    $parts[] = trim($part['text']);
                }
            }
        }

        if (!empty($parts)) {
            $full_text = trim(implode(' ', $parts));
            if (!empty($full_text)) {
                return $full_text;
            }
        }

        return new WP_Error('fmrseo_ai_empty_response', $this->describe_empty_response($finish_reason));
    }

    /**
     * Builds a descriptive error message for an empty Gemini response.
     *
     * @param string $finish_reason Gemini's finishReason value, if any.
     * @return string
     */
    private function describe_empty_response($finish_reason)
    {
        if ('MAX_TOKENS' === $finish_reason) {
            return esc_html__('Gemini used all available output tokens for internal reasoning and produced no filename. Try increasing the model output limit or use a different model.', 'file-media-renamer-for-seo');
        }

        $safety_reasons = array('SAFETY', 'RECITATION', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII');
        if (in_array($finish_reason, $safety_reasons, true)) {
            return sprintf(
                /* translators: %s is Gemini's finish reason code. */
                esc_html__('Gemini declined to generate a filename for this file (reason: %s).', 'file-media-renamer-for-seo'),
                sanitize_text_field($finish_reason)
            );
        }

        if (!empty($finish_reason)) {
            return sprintf(
                /* translators: %s is Gemini's finish reason code. */
                esc_html__('Gemini did not return a filename (finish reason: %s).', 'file-media-renamer-for-seo'),
                sanitize_text_field($finish_reason)
            );
        }

        return esc_html__('Gemini did not return a filename.', 'file-media-renamer-for-seo');
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
     * Checks whether the file is a plain-text type Gemini can read directly.
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
