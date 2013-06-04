Clear Demon Framework for PHP
=============================

*CDF* is aimed to be a simple, light-weight and non-obtrustive collection of classes that provide easy access and safe
functionality for any application developed in PHP. It is designed to augment the built-in systems that PHP exposes, wrapping
around common everyday tasks into easy-to-use classes.

Features
--------

* Support for web sites, web APIs and console-based PHP applications
* Extensive data helper functions that convert between low-level data types and ensure the returned value is what it is supposed to be (an integer or string)
* Exception handling and framework-global exceptions usable by applications
* Abstract, class-based data objects with full support for validation, binary objects and encapsulation into MySQL
* .ini file configuration support
* Wrappers for all the popular memory cache solutions, such as memcached
* Object-orientated JSON functions with support to read/write direct from/to the request/response

What It Isn't
-------------

While CDF is fully object-orientated, itself is not a framework that does everything for you. What you won't find are high-level
handlers for activities like HTML templating, JavaScript or Ajax framework integration, user authentication APIs and so on.
It is designed to be for low-level usage, but will happily work alongside any framework you desire (such as Smarty).

Usage
-----

Clone this repository into something like an `include` directory in your project and just `require_once` the bits that are
contextually-relevant to your source. Example:

  <?php
  require_once 'include/cdf/framework/core/CDFDataHelper.php';
  
  echo CDFDataHelper::AsStringSafe('<h1>Hello world!</h1>');

In this example, this will output `Hello world!` without the <h1> tags.

Import
------

This project was migrated from CodePlex in June 2013.
