# Web Composer

![Web Composer Screenshot](https://raw.githubusercontent.com/BurakBoz/web-composer/main/screenshot.png)

## Overview

Web Composer is a user-friendly web interface that simplifies the process of installing and managing Composer packages for your PHP projects. This tool proves especially valuable in hosting environments and servers where command-line access is restricted.

## License

Web Composer is open-source software and is licensed under the [Attribution 4.0 International License](http://creativecommons.org/licenses/by/4.0/). Please review the license document for detailed information.

## Getting Started

To begin using Web Composer, follow these simple steps:

1. **Upload**: Upload the `composer.php` file to the root directory of your project on your web server.

2. **Access**: Access the Web Composer interface through your web browser by navigating to `http://yourwebsite.com/path-to-web-composer/composer.php`.

3. **Manage Dependencies**: Utilize Web Composer to effortlessly manage your project's Composer dependencies. By default, development dependencies are not installed, and the autoloader is optimized for performance.

## Laravel integration
1. Put `composer.php` next to your composer.json or project root directory.
2. Open your `public/index.php` file.
3. Find
```php
require __DIR__.'/../vendor/autoload.php';
```
4. Replace with
```php
if(!@include __DIR__.'/../vendor/autoload.php')
{
    require __DIR__."/../composer.php";
    exit();
}
```
5. now delete `vendor/` folder on your project
```bash
rm -rf vendor/
```
6. Open your web site in web browser `http://yourwebsite.com/`
7. It should work seamlessly.

## Contributing

We welcome contributions to Web Composer! If you wish to contribute to the project, please visit our [GitHub repository](https://github.com/BurakBoz/web-composer) to get started. Your contributions are greatly appreciated.

## Author

Web Composer is developed and maintained by [Burak Boz](https://www.burakboz.net). For more information about the author and their work, visit the provided link.

---

**Note**: As per the license requirements, it's important to maintain the license and author information. When contributing to the project, please respect the license rules and author rights.
