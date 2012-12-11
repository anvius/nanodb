<?php
	/************************************************ 
	 *                NanoDB v.9.01
	 *     Ultra Lightweight Text File Data Base
	 *               PHP CRUD system. 
	 *     Copyright (c) 2009 Antonio Villamarin
	 *                www.zeura.com
	 *             License CC 3.0BY-SA
	 * http://creativecommons.org/licenses/by-sa/3.0/
	 ***********************************************/

	// Configuracion	
	define( 'ND_DIR', dirname( __FILE__ ).'/' );
	define( 'ND_DEBUG', FALSE );

	// Errores
	define( 'ND_ERROR_CANNOT_CREATE', '(1) El fichero ya existe.' );
	define( 'ND_ERROR_CONDITION', '(2) Condici&oacute;n err&oacute;nea.' );
	define( 'ND_FILE_NOT_EXISTS', '(3) El fichero no existe.' );
	
	function nd_file( $name ) {
		return( ND_DIR.$name.'.ndb' );
	}
	
	function nd_exists( $name ) {
		return( file_exists( nd_file( $name ) ) );
	}
	
	function nd_create( $name ) {
		if( file_exists( nd_file( $name ) ) ) {
			nd_error( ND_ERROR_CANNOT_CREATE.' ['.nd_file( $name ).']' );
			return( FALSE );
		}
		$json = array(
			'name' => $name,
			'data' => array(),
			'count' => 0,
			'last_modified' => time(),
			'creation' => time(),
			'high_id' => 0
		);
		nd_put( $name, $json );
	}
	
	function nd_drop( $name ) {
		if( file_exists( nd_file( $name ) ) ) {
			unlink( nd_file( $name ) );
		} else {
			nd_error( ND_FILE_NOT_EXISTS.' ['.nd_file( $name ).']' );
		}
	}
	
	function nd_put( $name, $value ) {
		$value['last_modified'] = time();
		file_put_contents( nd_file( $name ), base64_encode( json_encode( $value ) ) );
		chmod( nd_file( $name ), 0750 );
	}
	
	function nd_get( $name ) {
		if( file_exists( nd_file( $name ) ) ) {
			return( json_decode( base64_decode( file_get_contents( nd_file( $name ) ) ), TRUE ) );
		} else {
			nd_error( ND_FILE_NOT_EXISTS.' ['.nd_file( $name ).']' );
		}
	}
	
	function nd_select( $name, $cond = NULL ) {
		$json = nd_get( $name );
		if( is_string( $cond ) ) {
			$json = _nd_evaluate_condition( $json, $cond );
		}
		return( $json );
	}
	
	function nd_delete( $name, $cond ) {
		$json = nd_get( $name );
		$counter = $json['count'];
		$json = _nd_evaluate_condition( $json, $cond, TRUE );
		nd_put( $name, $json );
		return( $counter - $json['count'] );
	}
	
	function nd_update( $name, $fields, $cond = NULL ) {
		$json = nd_get( $name );
		$results = $json;
		if( is_string( $cond ) ) {
			$results = _nd_evaluate_condition( $json, $cond );
		}
		$counter = 0;
		if( $results['count'] > 0 ) {
			foreach( $results['data'] as $key => $line ) {
				foreach( $line as $fname => $fvalue ) {
					$json['data'][$key][$fname] = (isset($fields[$fname])?$fields[$fname]:$json['data'][$key][$fname]);
				}
				$counter++;
			}
		}
		nd_put( $name, $json );
		return( $counter );
	}
	
	function nd_insert( $name, $fields ) {
		$json = nd_get( $name );
		if( is_array( $fields[0] ) ) {
			foreach( $fields as $record ) {
				$record['_id'] = $json['high_id']+1;
				$json['data'][] = $record;
				$json['count'] = count( $json['data'] );
				$json['high_id']++;
			}
		} else {
			$fields['_id'] = $json['high_id']+1;
			$json['data'][] = $fields;
			$json['count'] = count( $json['data'] );
			$json['high_id']++;
		}
		nd_put( $name, $json );
	}
	
	function _nd_evaluate_condition( $json, $cond, $not = FALSE ) {
		$c = _nd_evaluate_operation( $cond );
		if( $c === FALSE ) {
			return( $json );
		}
		if( count( $json['data'] ) > 0 ) {
			$result = array();
			foreach( $json['data'] as $key => $line ) {
				switch( $c[2] ) {
					case '==':
						if( $line[$c[0]] == $c[1] && !$not ) {
							$result[$key] = $line;
						} elseif( $line[$c[0]] != $c[1] && $not ) {
							$result[$key] = $line;
						}
						break;
					case '!=':
						if( $line[$c[0]] != $c[1] && !$not ) {
							$result[$key] = $line;
						} elseif( $line[$c[0]] == $c[1] && $not ) {
							$result[$key] = $line;
						}
						break;
					case '>=':
						if( $line[$c[0]] >= $c[1] && !$not ) {
							$result[$key] = $line;
						} elseif( $line[$c[0]] < $c[1] && $not ) {
							$result[$key] = $line;
						}
						break;
					case '<=':
						if( $line[$c[0]] <= $c[1] && !$not ) {
							$result[$key] = $line;
						} elseif( $line[$c[0]] > $c[1] && $not ) {
							$result[$key] = $line;
						}
						break;
					case '>':
						if( $line[$c[0]] > $c[1] && !$not ) {
							$result[$key] = $line;
						} elseif( $line[$c[0]] <= $c[1] && $not ) {
							$result[$key] = $line;
						}
						break;
					case '<':
						if( $line[$c[0]] < $c[1] && !$not ) {
							$result[$key] = $line;
						} elseif( $line[$c[0]] >= $c[1] && $not ) {
							$result[$key] = $line;
						}
						break;
					case '[]':
						$preg = ( preg_match( $c[1], $line[$c[0]] ) > 0 );
						if( !$preg XOR !$not ) {
							$result[$key] = $line;
						}
						break;
				}
			}
			$json['data'] = $result;
			$json['count'] = count( $result );
			
		}
		return( $json );
	}
	
	function _nd_evaluate_operation( $cond ) {
		$conds = array( '==', '!=', '>=', '<=', '>', '<', '[]' );
		$j = FALSE;
		$i = 0;
		while( $j === FALSE && $i < count( $conds ) ) {
			$j = strpos( $cond, $conds[$i] );
			$i++;
		}
		$i--;
		if( $j !== FALSE ) {
			$r = explode( $conds[$i], $cond );
			$r[0] = trim( $r[0] );
			$r[1] = trim( $r[1] );
			$r[2] = $conds[$i];
			return( $r );
		} else {
			nd_error( ND_ERROR_CONDITION.' ['.$cond.']' );
			return( FALSE );
		}		
	}
	
	function nd_error( $error = NULL ) {
		static $errors = array();
		if( $error != NULL ) {
			$errors[] = $error;
			if( ND_DEBUG ) echo( 'ND Error: '.$error.'<br/>'."\n" );
		} else {
			return( $errors );
		}
	}

?>
