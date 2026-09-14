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
		$stats = array();
		$completed_matches = 0;
		$total_matches     = 0;
		foreach ( $group_schedule['players'] as $player ) {
			$stats[ (int) $player->ID ] = array(
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
				if ( 0 === $game_totals['winner'] ) {
					$stats[ $first_id ]['wins']++;
					$stats[ $second_id ]['losses']++;
				} else {
					$stats[ $second_id ]['wins']++;
					$stats[ $first_id ]['losses']++;
				}
			}
		}

		$standings = array_values( $stats );
		usort( $standings, array( __CLASS__, 'compare_standings' ) );
		return array( 'standings' => $standings, 'complete' => $completed_matches === $total_matches );
	}

	public static function is_round_complete( $round, $scores, $games ) {
		if ( empty( $round ) ) {
			return false;
		}

		foreach ( $round as $match ) {
			$first_id  = (int) $match[0]->ID;
			$second_id = (int) $match[1]->ID;
			$key       = self::pair_key( $first_id, $second_id );
			if ( ! isset( $scores[ $key ] ) ) {
				return false;
			}
			$game_totals = self::game_totals( $scores[ $key ], $games );
			if ( null === $game_totals['winner'] ) {
				return false;
			}
		}

		return true;
	}

	public static function standings_up_to_round( $group_schedule, $scores, $games, $round_index ) {
		$stats = array();
		foreach ( $group_schedule['players'] as $player ) {
			$stats[ (int) $player->ID ] = array(
				'player'         => $player,
				'wins'           => 0,
				'losses'         => 0,
				'games_for'      => 0,
				'games_against'  => 0,
				'points_for'     => 0,
				'points_against' => 0,
			);
		}

		foreach ( $group_schedule['rounds'] as $index => $round ) {
			if ( $index > $round_index ) {
				break;
			}
			foreach ( $round as $match ) {
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
				$stats[ $first_id ]['games_for']       += $game_totals['games'][0];
				$stats[ $first_id ]['games_against']   += $game_totals['games'][1];
				$stats[ $second_id ]['games_for']      += $game_totals['games'][1];
				$stats[ $second_id ]['games_against']  += $game_totals['games'][0];
				$stats[ $first_id ]['points_for']      += $game_totals['points'][0];
				$stats[ $first_id ]['points_against']  += $game_totals['points'][1];
				$stats[ $second_id ]['points_for']     += $game_totals['points'][1];
				$stats[ $second_id ]['points_against'] += $game_totals['points'][0];
				if ( 0 === $game_totals['winner'] ) {
					$stats[ $first_id ]['wins']++;
					$stats[ $second_id ]['losses']++;
				} else {
					$stats[ $second_id ]['wins']++;
					$stats[ $first_id ]['losses']++;
				}
			}
		}

		$standings = array_values( $stats );
		usort( $standings, array( __CLASS__, 'compare_standings' ) );
		return $standings;
	}

	private static function compare_standings( $first, $second ) {
		$fields = array(
			'wins' => 1,
			'games_for' => 1,
			'games_against' => -1,
			'points_for' => 1,
			'points_against' => -1,
		);
		foreach ( $fields as $field => $direction ) {
			$value = $direction * ( $first[ $field ] - $second[ $field ] );
			if ( 0 !== $value ) {
				return $value > 0 ? -1 : 1;
			}
		}
		$title_difference = strcasecmp( $first['player']->post_title, $second['player']->post_title );
		return 0 !== $title_difference ? $title_difference : ( (int) $first['player']->ID - (int) $second['player']->ID );
	}

	private static function crossover_stages( $group_count ) {
		if ( 2 === $group_count ) {
			return array(
				array( 'id' => 'final', 'label' => 'Final', 'matches' => array( array( 'id' => 'final-1', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 1, 'place' => 1 ) ) ) ) ),
				array( 'id' => 'third-place', 'label' => 'Third-place match', 'matches' => array( array( 'id' => 'third-place-1', 'players' => array( array( 'group' => 0, 'place' => 2 ), array( 'group' => 1, 'place' => 2 ) ) ) ) ),
			);
		}
		if ( 3 === $group_count ) {
			return array(
				array( 'id' => 'winner-round', 'label' => 'Winner round', 'matches' => array(
					array( 'id' => 'winner-1', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 1, 'place' => 1 ) ) ),
					array( 'id' => 'winner-2', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 2, 'place' => 1 ) ) ),
					array( 'id' => 'winner-3', 'players' => array( array( 'group' => 1, 'place' => 1 ), array( 'group' => 2, 'place' => 1 ) ) ),
				) ),
			);
		}
		if ( 4 === $group_count ) {
			return array(
				array( 'id' => 'semifinals', 'label' => 'Semi-finals', 'matches' => array(
					array( 'id' => 'semi-1', 'players' => array( array( 'group' => 0, 'place' => 1 ), array( 'group' => 3, 'place' => 1 ) ) ),
					array( 'id' => 'semi-2', 'players' => array( array( 'group' => 1, 'place' => 1 ), array( 'group' => 2, 'place' => 1 ) ) ),
				) ),
				array( 'id' => 'final', 'label' => 'Final', 'matches' => array( array( 'id' => 'final-1', 'players' => array( array( 'stage' => 'semifinals', 'match' => 0, 'result' => 'winner' ), array( 'stage' => 'semifinals', 'match' => 1, 'result' => 'winner' ) ) ) ) ),
				array( 'id' => 'third-place', 'label' => 'Third-place match', 'matches' => array( array( 'id' => 'third-place-1', 'players' => array( array( 'stage' => 'semifinals', 'match' => 0, 'result' => 'loser' ), array( 'stage' => 'semifinals', 'match' => 1, 'result' => 'loser' ) ) ) ) ),
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
			$stats = array();
			$completed_matches = 0;
			foreach ( $groups as $group ) {
				if ( isset( $group['complete'], $group['standings'][0]['player'] ) && $group['complete'] ) {
					$stats[ (int) $group['standings'][0]['player']->ID ] = array( 'player' => $group['standings'][0]['player'], 'wins' => 0, 'losses' => 0, 'games_for' => 0, 'games_against' => 0, 'points_for' => 0, 'points_against' => 0 );
				}
			}
			foreach ( $stages[0]['matches'] as $match ) {
				if ( ! $match['winner'] || ! isset( $stats[ (int) $match['players'][0]->ID ], $stats[ (int) $match['players'][1]->ID ], $scores[ $match['score_key'] ] ) ) {
					continue;
				}
				$totals = self::game_totals( $scores[ $match['score_key'] ], $games );
				$completed_matches++;
				$stats[ (int) $match['winner']->ID ]['wins']++;
				$stats[ (int) $match['players'][0]->ID ]['games_for'] += $totals['games'][0];
				$stats[ (int) $match['players'][0]->ID ]['games_against'] += $totals['games'][1];
				$stats[ (int) $match['players'][1]->ID ]['games_for'] += $totals['games'][1];
				$stats[ (int) $match['players'][1]->ID ]['games_against'] += $totals['games'][0];
				$stats[ (int) $match['players'][0]->ID ]['points_for'] += $totals['points'][0];
				$stats[ (int) $match['players'][0]->ID ]['points_against'] += $totals['points'][1];
				$stats[ (int) $match['players'][1]->ID ]['points_for'] += $totals['points'][1];
				$stats[ (int) $match['players'][1]->ID ]['points_against'] += $totals['points'][0];
			}
			if ( count( $stats ) !== 3 || 3 !== $completed_matches ) {
				return array();
			}
			$places = array_values( $stats );
			usort( $places, array( __CLASS__, 'compare_standings' ) );
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
