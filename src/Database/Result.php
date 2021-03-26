<?php

	namespace Darkish\Database;

	class Result extends Table implements \Iterator, \JsonSerializable, \ArrayAccess, \Serializable, \Countable {

		private $_records = [];
		private $_context;
		private $_position = 0;
		private $_totalCount = 0;
		private $_totalPages = 1;
		private $_page = 1;
		private $_quantity = 0;

		function __construct( $name, $records, $context = null, $totalCount = null, $quantity = 10, $page = 1 ) {
			$this->_records = $records;
			$this->_name = $name;
			$this->_context = $context;
			$this->_position = 0;
			if( is_null( $totalCount ) ) {
				$totalCount = count( $records );
			}
			$this->_totalCount = $totalCount;
			$this->_quantity = $quantity;
			$this->_totalPages = ceil( $totalCount / $quantity );
			$this->_page = $page;
		}

		function getTotalPages() {
			return $this->_totalPages;
		}

		function getTotalCount() {
			return $this->_totalCount;
		}

		function getPage() {
			return $this->_page;
		}

		function getQuantity() {
			return $this->_quantity;
		}

		function iterator() {
			foreach( $this->_records as &$record ) {
				yield new Model( $this->_name, $record, $this->_context );
			}
		}

		function first() {
			return empty( $this->_records ) ? null : $this[ 0 ];
		}

		function last() {
			return empty( $this->_records ) ? null : $this[ count( $this->_records ) - 1 ];
		}

		function toArray() {
			$result = [];
			foreach( $this->_records as &$record ) {
				$result[] = new Model( $this->_name, $record, $this->_context );
			}
			return $result;
		}

		function rewind() {
			$this->_position = 0;
		}

		function current() {
			return new Model( $this->_name, $this->_records[ $this->_position ], $this->_context );
		}

		function key() {
			return $this->_position;
		}

		function next() {
			++$this->_position;
		}

		function valid() {
			return isset( $this->_records[ $this->_position ] );
		}

		function jsonSerialize() {
			return $this->_records;
		}

		function offsetSet( $offset, $value ) {}

		function offsetExists( $offset ) {
			return isset( $this->_records[ $offset ] );
		}

		function offsetUnset( $offset ) {}

		function offsetGet( $offset ) {
			return isset( $this->_records[ $offset ] ) ? new Model( $this->_name, $this->_records[ $offset ], $this->_context ) : null;
		}

		function serialize() {
			return serialize([
				$this->_name,
				$this->_records,
				$this->_totalCount,
				$this->_quantity,
				$this->_page
			]);
		}

		function unserialize( $data ) {
			list( $this->_name, $this->_records, $this->_totalCount, $this->_quantity, $this->_page ) = unserialize( $data );
		}

		function count() {
			return count( $this->_records );
		}

		function pagination( $link ) {
			$pages = [];
			$currentRange = [($this->_page - 2 < 1 ? 1 : $this->_page - 2 ), ($this->_page + 2 > $this->_totalPages ? $this->_totalPages : $this->_page + 2 )];
			if( $this->_page > 1 ) {
				$pages[] = [
					'text' => 'Previous',
					'link' => sprintf($link, $this->_page - 1),
					'current' => false
				];
			}
			if( $this->_page > 3 ) {
				$pages[] = [
					'text' => 'First',
					'link' => sprintf($link, 1),
					'current' => false
				];
			}
			for( $i = $currentRange[0];$i <= $currentRange[1]; $i++) {
				$pages[] = [
					'text' => $i,
					'link' => sprintf($link, $i),
					'current' => $i == $this->_page
				];
			}
			if( $this->_page < $this->_totalPages - 2 ) {
				$pages[] = [
					'text' => 'Last',
					'link' => sprintf($link, $this->_totalPages),
					'current' => false
				];
			}
			if( $this->_page < $this->_totalPages) {
				$pages[] = [
					'text' => 'Next',
					'link' => sprintf($link, $this->_page +1),
					'current' => false
				];
			}
			return $pages;
		}
	}