# TYPO3 Extension "func"

## Introduction
The "func" extension brings the "Web->Functions" module to the TYPO3
backend. It acts as a container for other extension to hook in, the
two core extensions "wizard_crpages" and "wizard_sortpages" knows from
TYPO3 core version 8 and previous versions did that. With the integration
of these two extensions into the backend pages context menus, the "func"
extension became obsolete and has been removed from the core.

The extension is now available as composer package and TER extension
as backwards compatible layer if other extensions use the container
functionality of "func".

## Installation
The extension is typically set as "require" dependency within consuming
extensions in their ext_emconf.php:

```
    'constraints' => [
        ...
        'depends' => [
            'func' => '9.0.0-9.5.99',
        ],
    ],

```

The version constraint depends on the core compatibility constraints the extension
follows. For composer, a ''composer require friendsoftypo3/func'' should fit, maybe
as "require" in its composer.json file.

## Current state
The latest version here reflects a feature-complete state. There are bugs, we know,
there are possible feature requests - we know. But it's highly likely that this
extensions gets no new features, unless somebody steps up and continues the development
(see further below).

## Contribution
Feel free to submit any pull request, or add documentation, tests, as you please.
We will publish a new version every once in a while, depending on the amount of changes
and pull requests submitted.
​
## License
The extension is published under GPL v2+.
​
## Authors
A lot of contributors have been working on this area while this functionality was part of
the TYPO3 Core. This package is now maintained by a loose group of TYPO3 enthusiasts inside
the TYPO3 Community. Feel free to contact Benni Mack (benni.mack@typo3.org) for any questions
regarding "func".