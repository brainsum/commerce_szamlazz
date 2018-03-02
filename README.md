[![Build Status](https://travis-ci.org/brainsum/commerce_szamlazz.svg?branch=master)](https://travis-ci.org/brainsum/commerce_szamlazz)

# szamlazz.hu integration for Drupal Commerce 2.x

## Contents

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers
 * Supporting organisation

## Introduction
	
This module provides integration with szamlazz.hu invoide generation generation service.

The module creates and adds a new field to orders where it saves the generated invoice url, this field is also placed on the orders administration view, but it is hidden from user forms.

## Requirements
	
* Drupal Commerce (https://www.drupal.org/project/commerce)
* Drupal commerce Order (submodule of Drupal Commerce)

## Installation

Enable the module using drush or from the admin ui (admin/modules/install).
See: https://www.drupal.org/documentation/install/modules-themes/modules-8 for further information.


## Configuration

To be able to use the module you will have to create a user at https://www.szamlazz.hu
After the module is enabled and and you created a user, head over to the settings form (/admin/commerce/config/szamlazz) and fill in the necessary information that the API requires

