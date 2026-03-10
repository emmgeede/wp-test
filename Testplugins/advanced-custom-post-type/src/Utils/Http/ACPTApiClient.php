<?php

namespace ACPT\Utils\Http;

class ACPTApiClient
{
	const BASE_ACPT_URL = 'https://acpt.io/wp-json/api/v1';

    const ACPT_USER_AGENT = 'ACPTUserAgent/1.0';

	/**
	 * @param string $url
	 * @param array $data
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function call($url, $data = [])
	{
		$args = [
			'method'     => 'POST',
            'user-agent' => self::ACPT_USER_AGENT,
			'headers'    => [
				'Content-Type' => 'application/json',
                'User-Agent'   => self::ACPT_USER_AGENT,
			],
			'timeout'   => 3,
			'body'      => wp_json_encode($data),
			'sslverify' => false,
		];

		$finalUrl = self::BASE_ACPT_URL . $url;
		$response = wp_remote_post($finalUrl, $args);

		if (!is_wp_error($response)) {
			return json_decode(wp_remote_retrieve_body( $response), true);
		}

		throw new \Exception($response->get_error_message());
	}
}
