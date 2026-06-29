# Simple OAuth Password Grant

## Contents of this file

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Maintainers](#maintainers)

## Introduction

This module re-implements the `PasswordGrant` for the `simple_oauth` module.

> :warning: **WARNING**: This grant type MUST be used WITH GREAT CARE!
> The grant was removed from OAuth 2.0 Best Practices for a reason!
>
> Only use this for trusted, secure, first-party applications. If possible,
> the Authorization Code Grant shall always be preffered over this!

More info about the grant type can found in the [PHP League OAuth2 Server Docs](https://oauth2.thephpleague.com/authorization-server/resource-owner-password-credentials-grant/).

### Usage

To use this module, simply enable the **Password** grant type in your OAuth2
Consumer. You can then obtain an access token by requesting it with the
following payload:

```json
{
  "grant_type": "password",
  "client_id": "__your-client-id__",
  "client_secret": "__your-client-secret__",
  "username": "drupal_username_or_email",
  "password": "drupal_password"
}
```

**Important**
The username can either be the Drupal username, or the Drupal
user's email address!

## Requirements

The core module requires Drupal 9 or 10 and the following contrib modules:

- [Simple OAuth](https://www.drupal.org/project/simple_oauth)

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Maintainers

Current maintainers:

- Christoph Niedermoser ([@nimoatwoodway](https://www.drupal.org/u/nimoatwoodway))
- Christian Foidl ([@chfoidl](https://www.drupal.org/u/chfoidl))
