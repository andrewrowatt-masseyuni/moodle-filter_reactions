# Reactions filter (filter_reactions)

A Moodle text filter that renders interactive reaction widgets (thumbs up/down and star ratings) from simple markup tags embedded in content.

## Requirements

- Moodle 4.5 (2024100700)

## Installation

1. Copy the `reactions` folder to `filter/reactions/` in your Moodle installation.
2. Visit **Site administration > Notifications** to complete the installation.
3. Enable the filter at **Site administration > Plugins > Filters > Manage filters**.

## Usage

Insert reaction tags into any filtered text content (e.g. page resources, labels, forum posts):

- `{reactions:thumbs,myitemid}` — thumbs widget with a unique item identifier for persisting reactions.
- `{reactions:stars,myitemid}` — star rating widget with a unique item identifier.

An item identifier is required to save reactions to the database. Without one, the widget is displayed in a read-only state.

## Features

- **Thumbs up/down** — users can like or dislike content, with live counts.
- **Star ratings** — 1-5 star ratings with average and total count display.
- Reactions are stored per user, per context, per item.
- Guest users see widgets in read-only mode.

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright 2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
