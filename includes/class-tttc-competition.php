<?php
/**
 * Tournament standings and crossover calculations.
 *
 * @package TableTennisTournamentForClubs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TTTC_Competition {
	public static function calculate( $schedule, $scores, $games ) {
		$groups = array();
		foreach ( $schedule as $group_index => $group_schedule ) {
			$groups[ $group_index ] = self::standings_for_group( $group_schedule, $scores, $games );
		}

		$stages = self::crossover_stages( count( $groups ) );
		$resolved_stages = array();
		foreach ( $stages as $stage ) {
			$resolved_matches = array();
			foreach ( $stage['matches'] as $match ) {
				$players = array( self::resolve_reference( $match['players'][0], $groups, $resolved_stages, $scores, $games ), self::resolve_reference( $match['players'][1], $groups, $resolved_stages, $scores, $games ) );
				$match['players'] = $players;
				$match['score_key'] = self::score_key( $match['id'], $players );
				$match['winner'] = self::match_winner( $match['score_key'], $players, $scores, $games );
				$match['loser'] = self::match_loser( $match['score_key'], $players, $scores, $games );
				$resolved_matches[] = $match;
			}
			$stage['matches'] = $resolved_matches;
			$resolved_stages[] = $stage;
		}

		return array(
			'groups' => $groups,
			'stages' => $resolved_stages,
			'places' => self::places( $groups, $resolved_stages, $scores, $games ),
		);
	}

	private static function standings_for_group( $group_schedule, $scores, $games ) {
		$stats         = array();
		$players_by_id = array();
		$match_records = array();
		$completed_matches = 0;
		$total_matches     = 0;
		foreach ( $group_schedule['players'] as $player ) {
			$id                    = (int) $player->ID;
			$players_by_id[ $id ]  = $player;
			$stats[ $id ] = array(
				'player' => $player,
				'wins' => 0,
				'losses' => 0,
				'games_for' => 0,
				'games_against' => 0,
				'points_for' => 0,
				'points_against' => 0,
			);
		}

		foreach ( $group_schedule['rounds'] as $round ) {
			foreach ( $round as $match ) {
				$total_matches++;
				$first_id  = (int) $match[0]->ID;
				$second_id = (int) $match[1]->ID;
				$key       = self::pair_key( $first_id, $second_id );
				if ( ! isset( $scores[ $key ] ) ) {
					continue;
				}
				$game_totals = self::game_totals( $scores[ $key ], $games );
				if ( null === $game_totals['winner'] ) {
					continue;
				}
				$completed_matches++;
				$stats[ $first_id ]['games_for']       += $game_totals['games'][0];
				$stats[ $first_id ]['games_against']   += $game_totals['games'][1];
				$stats[ $second_id ]['games_for']      += $game_totals['games'][1];
				$stats[ $second_id ]['games_against']  += $game_totals['games'][0];
				$stats[ $first_id ]['points_for']      += $game_totals['points'][0];
				$stats[ $first_id ]['points_against']  += $game_totals['points'][1];
				$stats[ $second_id ]['points_for']     += $game_totals['points'][1];
				$stats[ $second_id ]['points_against'] += $game_totals['points'][0];
				$winner_id = 0 === $game_totals['winner'] ? $first_id : $second_id;
				if ( $winner_id === $first_id ) {
					$stats[ $first_id ]['wins']++;
					$stats[ $second_id ]['losses']++;
				} else {
					$stats[ $second_id ]['wins']++;
					$stats[ $first_id ]['losses']++;
				}
				$match_records[] = array(
					'ids'       => array( $first_id, $second_id ),
					'winner_id' => $winner_id,
					'games'     => $game_totals['games'],
					'points'    => $game_totals['points'],
				);
			}
		}

		$standings = array();
		foreach ( self::rank_group_players( array_keys( $stats ), $match_records, $players_by_id ) as $id ) {
			$standings[] = $stats[ $id ];
		}
		return array( 'standings' => $standings, 'complete' => $completed_matches === $total_matches );
	}

	/**
	 * Ranks players per the ITTF group tie-break order: match wins, then a head-to-head
	 * mini-league (wins, game ratio, point ratio) restricted to just the tied players, recursively.
	 */
	private static function rank_group_players( $ids, $match_records, $players_by_id ) {
		if ( count( $ids ) <= 1 ) {
			return $ids;
		}
		$sub_stats = self::subgroup_stats( $ids, $match_records );
		$buckets   = array();
		foreach ( $ids as $id ) {
			$buckets[ $sub_stats[ $id ]['wins'] ][] = $id;
		}
		krsort( $buckets, SORT_NUMERIC );

		$ordered = array();
		foreach ( $buckets as $bucket ) {
			if ( 1 === count( $bucket ) ) {
				$ordered[] = $bucket[0];
			} elseif ( count( $bucket ) === count( $ids ) ) {
				// Wins didn't separate anyone in this subset: fall back to game/point ratios among them.
				$ordered = array_merge( $ordered, self::rank_by_ratio( $bucket, $sub_stats, $players_by_id ) );
			} else {
				$ordered = array_merge( $ordered, self::rank_group_players( $bucket, $match_records, $players_by_id ) );
			}
		}
		return $ordered;
	}

	private static function subgroup_stats( $ids, $match_records ) {
		$stats = array();
		foreach ( $ids as $id ) {
			$stats[ $id ] = array( 'wins' => 0, 'games_for' => 0, 'games_against' => 0, 'points_for' => 0, 'points_against' => 0 );
		}
		$id_lookup = array_flip( $ids );
		foreach ( $match_records as $record ) {
			list( $first_id, $second_id ) = $record['ids'];
			if ( ! isset( $id_lookup[ $first_id ], $id_lookup[ $second_id ] ) ) {
				continue;
			}
			$stats[ $first_id ]['games_for']       += $record['games'][0];
			$stats[ $first_id ]['games_against']   += $record['games'][1];
			$stats[ $second_id ]['games_for']      += $record['games'][1];
			$stats[ $second_id ]['games_against']  += $record['games'][0];
			$stats[ $first_id ]['points_for']      += $record['points'][0];
			$stats[ $first_id ]['points_against']  += $record['points'][1];
			$stats[ $second_id ]['points_for']     += $record['points'][1];
			$stats[ $second_id ]['points_against'] += $record['points'][0];
			$stats[ $record['winner_id'] ]['wins']++;
		}
		return $stats;
	}

	private static function rank_by_ratio( $ids, $stats, $players_by_id ) {
		usort(
			$ids,
			function( $a, $b ) use ( $stats, $players_by_id ) {
				$game_ratio = self::compare_ratio( $stats[ $a ]['games_for'], $stats[ $a ]['games_against'], $stats[ $b ]['games_for'], $stats[ $b ]['games_against'] );
				if ( 0 !== $game_ratio ) {
					return $game_ratio;
				}
				$point_ratio = self::compare_ratio( $stats[ $a ]['points_for'], $stats[ $a ]['points_against'], $stats[ $b ]['points_for'], $stats[ $b ]['points_against'] );
				if ( 0 !== $point_ratio ) {
					return $point_ratio;
				}
				// Still fully tied: ITTF resolves this by drawing lots, so fall back to a stable, deterministic order.
				$title_difference = strcasecmp( $players_by_id[ $a ]->post_title, $players_by_id[ $b ]->post_title );
				return 0 !== $title_difference ? $title_difference : ( $a - $b );
			}
		);
		return $ids;
	}

	/**
	 * Compares two for/against ratios via cross-multiplication (avoids float error); a zero denominator is treated as an infinite ratio.
	 */
	private static function compare_ratio( $a_for, $a_against, $b_for, $b_against ) {
		if ( 0 === $a_against && 0 === $b_against ) {
			return $b_for <=> $a_for;
		}
		if ( 0 === $a_against ) {
			return -1;
		}
		if ( 0 === $b_against ) {
			return 1;
		}
		return ( $b_for * $a_against ) <=> ( $a_for * $b_against );
	}

	private static function crossover_stages( $group_count ) {
		if ( 2 === $group_count ) {
			return array(
				array( 'id' => 'final', 'label' => __( 'Final', 'table-tennis-tournament-for-clubs' ), 'matches' => array( array( 'id' => 'final-1', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 1, 'place' => 1 ) ) ) ) ),
				array( 'id' => 'third-place', 'label' => __( 'Third-place match', 'table-tennis-tournament-for-clubs' ), 'matches' => array( array( 'id' => 'third-place-1', 'players' => array( array( 'group' => 0, 'place' => 2 ), array( 'group' => 1, 'place' => 2 ) ) ) ) ),
			);
		}
		if ( 3 === $group_count ) {
			return array(
				array( 'id' => 'winner-round', 'label' => __( 'Winner round', 'table-tennis-tournament-for-clubs' ), 'matches' => array(
					array( 'id' => 'winner-1', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 1, 'place' => 1 ) ) ),
					array( 'id' => 'winner-2', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 2, 'place' => 1 ) ) ),
					array( 'id' => 'winner-3', 'players' => array( array( 'group' => 1, 'place' => 1 ), array( 'group' => 2, 'place' => 1 ) ) ),
				) ),
			);
		}
		if ( 4 === $group_count ) {
			return array(
				array( 'id' => 'semifinals', 'label' => __( 'Semi-finals', 'table-tennis-tournament-for-clubs' ), 'matches' => array(
					array( 'id' => 'semi-1', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 3, 'place' => 1 ) ) ),
					array( 'id' => 'semi-2', 'players' => array( array( 'group' => 1, 'place' => 1 ), array( 'group' => 2, 'place' => 1 ) ) ),
				) ),
				array( 'id' => 'final', 'label' => __( 'Final', 'table-tennis-tournament-for-clubs' ), 'matches' => array( array( 'id' => 'final-1', 'players' => array( array( 'stage' => 'semifinals', 'match' => 0, 'result' => 'winner' ), array( 'stage' => 'semifinals', 'match' => 1, 'result' => 'winner' ) ) ) ) ),
				array( 'id' => 'third-place', 'label' => __( 'Third-place match', 'table-tennis-tournament-for-clubs' ), 'matches' => array( array( 'id' => 'third-place-1', 'players' => array( array( 'stage' => 'semifinals', 'match' => 0, 'result' => 'loser' ), array( 'stage' => 'semifinals', 'match' => 1, 'result' => 'loser' ) ) ) ) ),
			);
		}
		return array();
	}

	private static function resolve_reference( $reference, $groups, $stages, $scores, $games ) {
		if ( isset( $reference['group'] ) ) {
			$index = (int) $reference['group'];
			$place = (int) $reference['place'] - 1;
			return isset( $groups[ $index ]['complete'], $groups[ $index ]['standings'][ $place ]['player'] ) && $groups[ $index ]['complete'] ? $groups[ $index ]['standings'][ $place ]['player'] : null;
		}
		if ( isset( $reference['stage'], $reference['match'], $reference['result'] ) ) {
			foreach ( $stages as $stage ) {
				if ( $stage['id'] !== $reference['stage'] || ! isset( $stage['matches'][ $reference['match'] ] ) ) {
					continue;
				}
				$match = $stage['matches'][ $reference['match'] ];
				return 'winner' === $reference['result'] ? $match['winner'] : $match['loser'];
			}
		}
		return null;
	}

	private static function match_winner( $score_key, $players, $scores, $games ) {
		if ( ! $players[0] || ! $players[1] || ! isset( $scores[ $score_key ] ) ) {
			return null;
		}
		$totals = self::game_totals( $scores[ $score_key ], $games );
		return null === $totals['winner'] ? null : $players[ $totals['winner'] ];
	}

	private static function match_loser( $score_key, $players, $scores, $games ) {
		$winner = self::match_winner( $score_key, $players, $scores, $games );
		if ( ! $winner ) {
			return null;
		}
		return (int) $winner->ID === (int) $players[0]->ID ? $players[1] : $players[0];
	}

	private static function game_totals( $match_scores, $games ) {
		$game_wins = array( 0, 0 );
		$points    = array( 0, 0 );
		for ( $game = 0; $game < (int) $games; $game++ ) {
			if ( ! isset( $match_scores[ $game ] ) || ! is_array( $match_scores[ $game ] ) || '' === (string) $match_scores[ $game ][0] || '' === (string) $match_scores[ $game ][1] ) {
				continue;
			}
			$first  = (int) $match_scores[ $game ][0];
			$second = (int) $match_scores[ $game ][1];
			$points[0] += $first;
			$points[1] += $second;
			if ( $first > $second ) {
				$game_wins[0]++;
			} elseif ( $second > $first ) {
				$game_wins[1]++;
			}
		}
		$required = (int) ceil( (int) $games / 2 );
		return array( 'winner' => max( $game_wins ) >= $required ? ( $game_wins[0] > $game_wins[1] ? 0 : 1 ) : null, 'games' => $game_wins, 'points' => $points );
	}

	private static function score_key( $id, $players ) {
		return 'crossover:' . $id;
	}

	private static function pair_key( $first_id, $second_id ) {
		$ids = array( (int) $first_id, (int) $second_id );
		sort( $ids, SORT_NUMERIC );
		return $ids[0] . '-' . $ids[1];
	}

	private static function places( $groups, $stages, $scores, $games ) {
		$places = array();
		if ( 1 === count( $groups ) ) {
			return isset( $groups[0]['complete'] ) && $groups[0]['complete'] ? $groups[0]['standings'] : array();
		}
		if ( 3 === count( $groups ) && isset( $stages[0] ) ) {
			$players_by_id = array();
			foreach ( $groups as $group ) {
				if ( isset( $group['complete'], $group['standings'][0]['player'] ) && $group['complete'] ) {
					$player = $group['standings'][0]['player'];
					$players_by_id[ (int) $player->ID ] = $player;
				}
			}
			$match_records = array();
			foreach ( $stages[0]['matches'] as $match ) {
				if ( ! $match['winner'] || ! isset( $players_by_id[ (int) $match['players'][0]->ID ], $players_by_id[ (int) $match['players'][1]->ID ], $scores[ $match['score_key'] ] ) ) {
					continue;
				}
				$totals          = self::game_totals( $scores[ $match['score_key'] ], $games );
				$match_records[] = array(
					'ids'       => array( (int) $match['players'][0]->ID, (int) $match['players'][1]->ID ),
					'winner_id' => (int) $match['winner']->ID,
					'games'     => $totals['games'],
					'points'    => $totals['points'],
				);
			}
			if ( count( $players_by_id ) !== 3 || 3 !== count( $match_records ) ) {
				return array();
			}
			foreach ( self::rank_group_players( array_keys( $players_by_id ), $match_records, $players_by_id ) as $id ) {
				$places[] = array( 'player' => $players_by_id[ $id ] );
			}
			return $places;
		}

		$final_stage       = null;
		$third_place_stage = null;
		foreach ( $stages as $stage ) {
			if ( 'final' === $stage['id'] ) {
				$final_stage = $stage;
			} elseif ( 'third-place' === $stage['id'] ) {
				$third_place_stage = $stage;
			}
		}
		if ( ! $final_stage || ! $third_place_stage || empty( $final_stage['matches'][0]['winner'] ) || empty( $final_stage['matches'][0]['loser'] ) || empty( $third_place_stage['matches'][0]['winner'] ) ) {
			return array();
		}
		return array(
			array( 'player' => $final_stage['matches'][0]['winner'] ),
			array( 'player' => $final_stage['matches'][0]['loser'] ),
			array( 'player' => $third_place_stage['matches'][0]['winner'] ),
		);
	}
}
