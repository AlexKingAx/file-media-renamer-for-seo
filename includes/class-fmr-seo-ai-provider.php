<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AI provider contract for filename generation.
 */
interface FMRSEO_AI_Provider_Interface
{
    /**
     * Generates an SEO filename (without extension) for an attachment.
     *
     * @param int   $attachment_id Attachment ID.
     * @param array $settings      AI settings.
     *
     * @return string|WP_Error
     */
    public function generate_name_for_attachment($attachment_id, $settings);
}

/**========================================================
 * OpenAI provider implementation.
 **========================================================*/
class FMRSEO_OpenAI_Provider implements FMRSEO_AI_Provider_Interface
{
    /**
     * OpenAI Responses API endpoint.
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/responses';
    /**
     * OpenAI Files API endpoint.
     */
    const FILES_ENDPOINT = 'https://api.openai.com/v1/files';

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
            return new WP_Error('fmrseo_ai_missing_key', esc_html__('OpenAI API key is missing.', 'file-media-renamer-for-seo'));
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
            $model = 'gpt-4.1-mini';
        }

        $website_info = isset($settings['website_info']) ? (string) $settings['website_info'] : '';
        $brand = isset($settings['brand']) ? (string) $settings['brand'] : '';

        if (strpos($mime_type, 'image/') === 0) {
            $prompt = $this->build_image_prompt($website_info, $brand);
            return $this->generate_from_image($api_key, $model, $file_path, $mime_type, $prompt);
        }

        if ($this->can_try_file_upload($mime_type, $extension)) {
            $prompt = $this->build_document_prompt($website_info, $brand);
            return $this->generate_from_uploaded_file($api_key, $model, $file_path, $mime_type, $prompt);
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
     * @param string $model     OpenAI model.
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

        $data_url = 'data:' . $mime_type . ';base64,' . base64_encode($file_data);
        $response = $this->send_responses_request(
            $api_key,
            $model,
            array(
                array(
                    'type' => 'input_text',
                    'text' => $prompt,
                ),
                array(
                    'type' => 'input_image',
                    'image_url' => $data_url,
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Generates output using uploaded file id.
     *
     * @param string $api_key   API key.
     * @param string $model     OpenAI model.
     * @param string $file_path File path.
     * @param string $mime_type Mime type.
     * @param string $prompt    Prompt.
     * @return string|WP_Error
     */
    private function generate_from_uploaded_file($api_key, $model, $file_path, $mime_type, $prompt)
    {
        $file_id = $this->upload_file_to_openai($api_key, $file_path, $mime_type);
        if (is_wp_error($file_id)) {
            return new WP_Error('fmrseo_ai_skipped_unreadable_document', esc_html__('Unable to analyze this file type with AI. File skipped.', 'file-media-renamer-for-seo'));
        }

        $response = $this->send_responses_request(
            $api_key,
            $model,
            array(
                array(
                    'type' => 'input_text',
                    'text' => $prompt,
                ),
                array(
                    'type' => 'input_file',
                    'file_id' => $file_id,
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Sends a request to the Responses API.
     *
     * @param string $api_key       API key.
     * @param string $model         OpenAI model.
     * @param array  $content_items Content list.
     * @return string|WP_Error
     */
    private function send_responses_request($api_key, $model, $content_items)
    {
        $response = wp_remote_post(
            self::API_ENDPOINT,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 60,
                'body' => wp_json_encode(
                    array(
                        'model' => $model,
                        'input' => array(
                            array(
                                'role' => 'user',
                                'content' => $content_items,
                            ),
                        ),
                        'max_output_tokens' => 80,
                    )
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('fmrseo_ai_request_failed', esc_html__('OpenAI request failed. Please try again.', 'file-media-renamer-for-seo'));
        }

        return $this->extract_output_from_response($response);
    }

    /**
     * Uploads a file to OpenAI Files API.
     *
     * @param string $api_key   API key.
     * @param string $file_path Absolute file path.
     * @param string $mime_type Mime type.
     * @return string|WP_Error
     */
    private function upload_file_to_openai($api_key, $file_path, $mime_type)
    {
        $file_contents = $this->read_local_file_contents($file_path);
        if (is_wp_error($file_contents)) {
            return $file_contents;
        }

        $filename = basename($file_path);
        $boundary = '----fmrseo' . wp_generate_password(24, false, false);

        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="purpose"' . "\r\n\r\n";
        $body .= "user_data\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . str_replace('"', '', $filename) . '"' . "\r\n";
        $body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        $response = wp_remote_post(
            self::FILES_ENDPOINT,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ),
                'timeout' => 90,
                'body' => $body,
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('fmrseo_ai_upload_failed', esc_html__('OpenAI file upload failed.', 'file-media-renamer-for-seo'));
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $response_body = (string) wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            $default_message = esc_html__('OpenAI file upload failed.', 'file-media-renamer-for-seo');
            return new WP_Error('fmrseo_ai_upload_failed', $this->extract_error_message($response_body, $default_message));
        }

        $decoded = json_decode($response_body, true);
        if (!is_array($decoded) || empty($decoded['id']) || !is_string($decoded['id'])) {
            return new WP_Error('fmrseo_ai_upload_invalid_response', esc_html__('Invalid OpenAI file upload response.', 'file-media-renamer-for-seo'));
        }

        return sanitize_text_field($decoded['id']);
    }

    /**
     * Extracts the final output text from a Responses API response.
     *
     * @param array $response Raw WP HTTP response.
     * @return string|WP_Error
     */
    private function extract_output_from_response($response)
    {
        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            $default_message = esc_html__('OpenAI returned an error. Please check your API key, model, and usage limits.', 'file-media-renamer-for-seo');
            return new WP_Error('fmrseo_ai_api_error', $this->extract_error_message($body, $default_message));
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return new WP_Error('fmrseo_ai_invalid_response', esc_html__('Invalid OpenAI response format.', 'file-media-renamer-for-seo'));
        }

        if (!empty($payload['output_text']) && is_string($payload['output_text'])) {
            $output_text = trim($payload['output_text']);
            if (!empty($output_text)) {
                return $output_text;
            }
        }

        if (!empty($payload['output']) && is_array($payload['output'])) {
            $parts = array();

            foreach ($payload['output'] as $output_item) {
                if (empty($output_item['content']) || !is_array($output_item['content'])) {
                    continue;
                }

                foreach ($output_item['content'] as $content_item) {
                    if (!empty($content_item['text']) && is_string($content_item['text'])) {
                        $parts[] = trim($content_item['text']);
                    }
                }
            }

            if (!empty($parts)) {
                $full_text = trim(implode(' ', $parts));
                if (!empty($full_text)) {
                    return $full_text;
                }
            }
        }

        return new WP_Error('fmrseo_ai_empty_response', esc_html__('OpenAI did not return a filename.', 'file-media-renamer-for-seo'));
    }

    /**
     * Reads file contents from local disk.
     *
     * @param string $file_path Local file path.
     * @return string|WP_Error
     */
    private function read_local_file_contents($file_path)
    {
        if (function_exists('fmrseo_get_filesystem')) {
            $filesystem = fmrseo_get_filesystem();
            if (!is_wp_error($filesystem)) {
                $contents = $filesystem->get_contents($file_path);
                if (false !== $contents) {
                    return $contents;
                }
            }
        }

        $contents = @file_get_contents($file_path);
        if (false !== $contents) {
            return $contents;
        }

        return new WP_Error('fmrseo_ai_read_failed', esc_html__('Unable to read the attachment file.', 'file-media-renamer-for-seo'));
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
     * Checks whether file upload analysis can be attempted.
     *
     * @param string $mime_type  Mime type.
     * @param string $extension  File extension.
     * @return bool
     */
    private function can_try_file_upload($mime_type, $extension)
    {
        $uploadable_mimes = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/zip',
            'application/json',
            'application/xml',
            'text/xml',
            'text/plain',
            'text/csv',
            'text/markdown',
            'application/rtf',
        );

        if (in_array($mime_type, $uploadable_mimes, true)) {
            return true;
        }

        $uploadable_extensions = array(
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'odt',
            'ods',
            'txt',
            'csv',
            'json',
            'xml',
            'zip',
            'rtf',
        );

        return in_array($extension, $uploadable_extensions, true);
    }

    /**
     * Extracts OpenAI error messages if present.
     *
     * @param string $body            Raw response body.
     * @param string $default_message Fallback error message.
     *
     * @return string
     */
    private function extract_error_message($body, $default_message)
    {
        $decoded = json_decode($body, true);

        if (!empty($decoded['error']['message']) && is_string($decoded['error']['message'])) {
            return sanitize_text_field($decoded['error']['message']);
        }

        return $default_message;
    }
}

/**
 * Returns an AI provider instance.
 *
 * @param string $provider Provider key.
 *
 * @return FMRSEO_AI_Provider_Interface|WP_Error
 */
function fmrseo_get_ai_provider_instance($provider)
{
    if ('openai' === $provider) {
        return new FMRSEO_OpenAI_Provider();
    }

    return new WP_Error('fmrseo_ai_provider_not_supported', esc_html__('Selected AI provider is not supported.', 'file-media-renamer-for-seo'));
}
