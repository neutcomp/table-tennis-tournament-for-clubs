=== Table Tennis Tournament for Clubs ===
Contributors: club-tools
Tags: table tennis, tournament, players, clubs
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Manage table tennis club players, tournaments, and tournament participation from WordPress.

== Description ==

Table Tennis Tournament for Clubs provides a focused admin workflow for maintaining player ratings and contact details, creating tournaments, and assigning active players to each tournament.

Player emails are stored for club administration and are not shown in public shortcodes.

== Features ==

* Player records with name, rating, email, and active status.
* Tournament records with name, date, best of, player type, and status.
* Tournament Players screen for adding and removing participants.
* Inactive players cannot be assigned to new tournaments.
* Tournament Scores screen with three or five score fields per generated match.
* Score validation requiring at least 11 points and a two-point winning margin per game, plus a completed Best of 3 or Best of 5 match.
* Match scores are stored for administrators and are not shown on public tournament pages.
* Public tournament pages with groups and round-robin match schedules.
* Public shortcodes for tournament and active player lists.

== Installation ==

1. Upload the `table-tennis-tournament-for-clubs` folder to `wp-content/plugins/`.
2. Activate the plugin from Plugins in WordPress.
3. Use the Table Tennis menu to create players and tournaments.
4. Open Tournament Players, select a tournament, and save its participants.
5. Select Best of 3 or Best of 5 when creating a tournament, then use Scores in the tournament overview to enter match scores.

== Shortcodes ==

`[tttc_tournaments]` displays published tournaments and their assigned players.

Each published tournament title links to `/toernooi/{tournament-name}/{DD-MM-YYYY}`. The public tournament page lists active assigned players by rating, distributes them into groups using the tournament group rules, and shows every round-robin match in each group. Schedules are available for 4 to 28 players.

Optional attributes:

* `[tttc_tournaments limit="10"]`
* `[tttc_tournaments id="123"]`

`[tttc_players]` displays active published players and their ratings.

Optional attribute:

* `[tttc_players limit="50"]`

== Tournament statuses ==

Draft, Upcoming, Active, Completed, and Cancelled are available. Draft tournaments are managed in the admin area but are not shown by public shortcodes.

== Privacy ==

Player email addresses are available to administrators in WordPress and are never rendered by the public shortcodes.
