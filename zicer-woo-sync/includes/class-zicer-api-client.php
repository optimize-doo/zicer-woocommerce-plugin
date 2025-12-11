<?php
/**
 * ZICER API Client
 *
 * Handles all API communication with ZICER marketplace including rate limiting.
 *
 * @package Zicer_Woo_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Zicer_API_Client
 */
class Zicer_API_Client {

    /**
     * Singleton instance
     *
     * @var Zicer_API_Client|null
     */
    private static $instance = null;

    /**
     * API token
     *
     * @var string
     */
    private $api_token;

    /**
     * Rate limit total
     *
     * @var int
     */
    private $rate_limit_limit = 60;

    /**
     * Rate limit remaining
     *
     * @var int
     */
    private $rate_limit_remaining = 60;

    /**
     * Rate limit reset timestamp
     *
     * @var int
     */
    private $rate_limit_reset = 0;

    /**
     * Get singleton instance
     *
     * @return Zicer_API_Client
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->api_token = get_option('zicer_api_token', '');
    }

    /**
     * Set API token
     *
     * @param string $token API token.
     */
    public function set_token($token) {
        $this->api_token = $token;
    }

    /**
     * Make API request
     *
     * @param string $method       HTTP method.
     * @param string $endpoint     API endpoint.
     * @param array  $data         Request data.
     * @param bool   $is_multipart Whether request is multipart.
     * @return array|WP_Error
     */
    public function request($method, $endpoint, $data = null, $is_multipart = false) {
        // Check rate limit
        if ($this->rate_limit_remaining <= 0 && time() < $this->rate_limit_reset) {
            $wait = $this->rate_limit_reset - time();
            return new WP_Error(
                'rate_limit',
                sprintf(
                    /* translators: %d: seconds to wait */
                    __('Rate limit reached. Please try again in %d seconds.', 'zicer-woo-sync'),
                    $wait
                )
            );
        }

        $url = ZICER_API_BASE_URL . $endpoint;

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($data !== null) {
            if ($is_multipart) {
                $args['headers']['Content-Type'] = 'multipart/form-data';
                $args['body'] = $data;
            } else {
                $content_type = ($method === 'PATCH')
                    ? 'application/merge-patch+json'
                    : 'application/json';
                $args['headers']['Content-Type'] = $content_type;
                $args['body'] = wp_json_encode($data);
            }
        }

        $debug = get_option('zicer_debug_logging', '0') === '1';

        if ($debug) {
            Zicer_Logger::log('debug', "API: $method $endpoint", [
                'body' => $data,
            ]);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Zicer_Logger::log('error', 'API request failed: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($debug) {
            Zicer_Logger::log('debug', "API response: $code", [
                'endpoint' => $endpoint,
            ]);
        }

        // Update rate limit info from headers
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['x-ratelimit-limit'])) {
            $this->rate_limit_limit = (int) $headers['x-ratelimit-limit'];
        }
        if (isset($headers['x-ratelimit-remaining'])) {
            $this->rate_limit_remaining = (int) $headers['x-ratelimit-remaining'];
        }
        if (isset($headers['x-ratelimit-reset'])) {
            $this->rate_limit_reset = (int) $headers['x-ratelimit-reset'];
        }

        // Persist rate limit info: on first request, when low, or every 30 seconds
        $stored = get_option('zicer_rate_limit_info', []);
        $last_update = $stored['updated'] ?? 0;
        $should_update = empty($stored)
            || $this->rate_limit_remaining < 10
            || (time() - $last_update) > 30;

        if ($should_update) {
            update_option('zicer_rate_limit_info', [
                'limit'     => $this->rate_limit_limit,
                'remaining' => $this->rate_limit_remaining,
                'reset'     => $this->rate_limit_reset,
                'updated'   => time(),
            ], false);
        }

        $code    = wp_remote_retrieve_response_code($response);
        $body    = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code >= 400) {
            $error_msg = isset($decoded['detail']) ? $decoded['detail'] :
                        (isset($decoded['message']) ? $decoded['message'] : 'Unknown error');
            Zicer_Logger::log('error', "API error ($code): $error_msg", [
                'endpoint' => $endpoint,
                'response' => $decoded,
            ]);
            return new WP_Error('api_error', $error_msg, ['status' => $code, 'body' => $decoded]);
        }

        return $decoded;
    }

    /**
     * GET request
     *
     * @param string $endpoint API endpoint.
     * @return array|WP_Error
     */
    public function get($endpoint) {
        return $this->request('GET', $endpoint);
    }

    /**
     * POST request
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @return array|WP_Error
     */
    public function post($endpoint, $data) {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * PATCH request
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @return array|WP_Error
     */
    public function patch($endpoint, $data) {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * DELETE request
     *
     * @param string $endpoint API endpoint.
     * @return array|WP_Error
     */
    public function delete($endpoint) {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Validate API connection
     *
     * @return array|WP_Error
     */
    public function validate_connection() {
        return $this->get('/me');
    }

    /**
     * Get shop info
     *
     * @return array|WP_Error
     */
    public function get_shop() {
        return $this->get('/shop');
    }

    /**
     * Get categories
     *
     * @param int $page Page number.
     * @return array|WP_Error
     */
    public function get_categories($page = 1) {
        return $this->get("/categories?page=$page&itemsPerPage=100");
    }

    /**
     * Get category suggestions for a title
     *
     * @param string $title Product title.
     * @return array|WP_Error
     */
    public function get_category_suggestions($title) {
        return $this->get('/categories/suggest?title=' . urlencode($title));
    }

    /**
     * Get regions
     *
     * @return array|WP_Error
     */
    public function get_regions() {
        return $this->get('/regions?exists[parent]=false&itemsPerPage=100');
    }

    /**
     * Get cities for a region
     *
     * @param string $region_id Region ID.
     * @return array|WP_Error
     */
    public function get_cities($region_id) {
        return $this->get("/regions/$region_id/cities?itemsPerPage=500");
    }

    /**
     * Create listing
     *
     * @param array $data Listing data.
     * @return array|WP_Error
     */
    public function create_listing($data) {
        return $this->post('/listings', $data);
    }

    /**
     * Update listing
     *
     * @param string $id   Listing ID.
     * @param array  $data Listing data.
     * @return array|WP_Error
     */
    public function update_listing($id, $data) {
        return $this->patch("/listings/$id", $data);
    }

    /**
     * Delete listing
     *
     * @param string $id Listing ID.
     * @return array|WP_Error
     */
    public function delete_listing($id) {
        return $this->delete("/listings/$id");
    }

    /**
     * Get listing
     *
     * @param string $id Listing ID.
     * @return array|WP_Error
     */
    public function get_listing($id) {
        return $this->get("/listings/$id");
    }

    /**
     * Get listings
     *
     * @param int $page     Page number.
     * @param int $per_page Items per page.
     * @return array|WP_Error
     */
    public function get_listings($page = 1, $per_page = 100) {
        return $this->get("/listings?page=$page&itemsPerPage=$per_page");
    }

    /**
     * Upload media to listing
     *
     * @param string $listing_id Listing ID.
     * @param string $file_path  File path.
     * @param int    $position   Image position.
     * @return array|WP_Error
     */
    public function upload_media($listing_id, $file_path, $position = 0) {
        $boundary     = wp_generate_password(24, false);
        $file_name    = basename($file_path);
        $file_type    = mime_content_type($file_path);
        $file_content = file_get_contents($file_path);

        $body  = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$file_name\"\r\n";
        $body .= "Content-Type: $file_type\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"position\"\r\n\r\n";
        $body .= $position . "\r\n";
        $body .= "--$boundary--\r\n";

        $args = [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type'  => "multipart/form-data; boundary=$boundary",
            ],
            'body'    => $body,
            'timeout' => 120,
        ];

        $debug = get_option('zicer_debug_logging', '0') === '1';

        if ($debug) {
            Zicer_Logger::log('debug', "API: POST /listings/$listing_id/media", [
                'file' => $file_name,
                'position' => $position,
            ]);
        }

        $response = wp_remote_request(
            ZICER_API_BASE_URL . "/listings/$listing_id/media",
            $args
        );

        if (is_wp_error($response)) {
            Zicer_Logger::log('error', 'Media upload failed: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($debug) {
            Zicer_Logger::log('debug', "API response: $code", [
                'endpoint' => "/listings/$listing_id/media",
            ]);
        }
        if ($code >= 400) {
            return new WP_Error('upload_error', 'Failed to upload media', ['status' => $code]);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Get rate limit status
     *
     * @return array
     */
    public function get_rate_limit_status() {
        return [
            'limit'     => $this->rate_limit_limit,
            'remaining' => $this->rate_limit_remaining,
            'reset'     => $this->rate_limit_reset,
            'reset_in'  => max(0, $this->rate_limit_reset - time()),
        ];
    }

    /**
     * Get user credit balance
     *
     * @return array|WP_Error
     */
    public function get_credits() {
        return $this->get('/credits');
    }

    /**
     * Get promotion price preview
     *
     * @param int  $days  Number of days for promotion.
     * @param bool $super Whether super premium promotion.
     * @return array|WP_Error Response with price, credits, canPromote.
     */
    public function get_promotion_price($days, $super = false) {
        return $this->post('/listings/promote/price', [
            'days'  => (int) $days,
            'super' => (bool) $super,
        ]);
    }

    /**
     * Promote a listing
     *
     * @param string $listing_id Listing ID.
     * @param int    $days       Number of days for promotion.
     * @param bool   $super      Whether super premium promotion.
     * @return array|WP_Error Response with promotion details.
     */
    public function promote_listing($listing_id, $days, $super = false) {
        return $this->post("/listings/$listing_id/promote", [
            'days'    => (int) $days,
            'super'   => (bool) $super,
            'promote' => true,
        ]);
    }
}
