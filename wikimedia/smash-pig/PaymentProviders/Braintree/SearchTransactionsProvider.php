<?php

namespace SmashPig\PaymentProviders\Braintree;

/**
 * This class allows testing connectivity with the Braintree GraphQL endpoint.
 * See the TestApi and GetReport script under PaymentProviders/Braintree/Maintenance.
 */
class SearchTransactionsProvider extends PaymentProvider {

	/**
	 * @param array $input
	 * @param ?string $after
	 * @return array
	 */
	public function searchTransactions( array $input, ?string $after ): array {
		$response = $this->api->searchTransactions( $input, $after );
		if ( isset( $response['errors'] ) ) {
			return $response['errors'];
		}
		$result = $response['data']['search']['transactions']['edges'];
		while ( $response['data']['search']['transactions']['pageInfo']['hasNextPage'] === true ) {
			$length = count( $result );
			$newAfter = $result[$length - 1]['cursor'];
			$response = $this->api->searchTransactions( $input, $newAfter );
			$result = array_merge( $result, $response['data']['search']['transactions']['edges'] );
		}
		return $result;
	}

	/**
	 * @param array $input
	 * @param ?string $after
	 * @return array
	 */
	public function searchRefunds( array $input, ?string $after ): array {
		$response = $this->api->searchRefunds( $input, $after );
		if ( isset( $response['errors'] ) ) {
			return $response['errors'];
		}
		$result = $response['data']['search']['refunds']['edges'];
		while ( $response['data']['search']['refunds']['pageInfo']['hasNextPage'] ) {
			$length = count( $result );
			$newAfter = $result[$length - 1]['cursor'];
			$response = $this->api->searchRefunds( $input, $newAfter );
			$result = array_merge( $result, $response['data']['search']['refunds']['edges'] );
		}
		return $result;
	}

	/**
	 * @param array $input
	 * @param ?string $after
	 * @return array
	 */
	public function searchDisputes( array $input, ?string $after ): array {
		$response = $this->api->searchDisputes( $input, $after );
		if ( isset( $response['errors'] ) ) {
			return $response['errors'];
		}
		$result = $response['data']['search']['disputes']['edges'];
		while ( $response['data']['search']['disputes']['pageInfo']['hasNextPage'] ) {
			$length = count( $result );
			$newAfter = $result[$length - 1]['cursor'];
			$response = $this->api->searchDisputes( $input, $newAfter );
			$result = array_merge( $result, $response['data']['search']['disputes']['edges'] );
		}
		return $result;
	}

}
