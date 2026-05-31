# ViewLatte Module

The ViewLatte module integrates the [Latte](https://latte.nette.org/) templating engine into the SkeletonApp. It provides a powerful, secure, and intuitive way to render HTML views with features like automatic escaping, image lazy loading, and HTML compression.

## Overview

The ViewLatte module acts as a view engine implementation for the SkeletonApp's view management system. It extends the core `ViewManager` and implements `ViewInterface`, allowing it to be seamlessly swapped or used alongside other view engines.

### Key Features:
- **Latte 3.x Integration**: Utilizes the latest version of the Latte templating engine.
- **Tracy Debugger**: Built-in support for the Tracy extension for easier template debugging.
- **Image Lazy Loading**: Automatically processes `<img>` and `<source>` tags to add lazy loading attributes and optionally integrates with an image processing model for compression.
- **HTML Compression**: Configurable minification of the rendered HTML output.
- **Plugin System**: Supports lazy-loaded custom functions and plugins.
- **Template Caching**: Compiled templates are cached for improved performance.

## Requirements

- **PHP**: >= 8.2
- **Latte**: 3.*
- **SkeletonApp Modules**: `skeleton-app/view` (provides `ViewManager` and `ViewInterface`).

## Project Structure

- `LatteView.php`: The main view engine implementation.
- `ServiceProvider.php`: Handles module registration and DI container setup.
- `composer.json`: Defines module dependencies and autoloading.

## Setup & Run Commands

The module is typically installed as part of the SkeletonApp via Composer.

1.  **Installation**:
    ```bash
    composer require skeleton-app/view-latte
    ```

2.  **Configuration**:
    Ensure the module's service provider is registered in your application's bootstrap process. The `ServiceProvider` automatically sets up the `ViewManager::View` service if it's not already defined.

3.  **Cache Directory**:
    The module uses `cache/template` in the project root to store compiled Latte templates. Ensure this directory is writable by the web server.

## Usage

### Rendering a Template
The view engine is typically accessed via the DI container:

```php
$view = $container->get('ViewManager::View');
return $view->render($response, 'index', ['name' => 'World']);
```

### Layouts
You can set a layout for your templates:
```php
$view->setLayout('layout/main.latte');
```

## Configuration (Env Vars / Config)

The module uses the application's configuration system (inherited from `ViewManager`):

- `template.path`: Root path for templates.
- `template.name`: Current template theme/folder name.
- `template.layout`: Default layout filename.
- `view.lazyload`: (bool) Enable/disable image lazy loading.
- `view.compressor`: (bool) Enable/disable HTML compression.

## Scripts

TODO: Document any module-specific CLI commands if implemented.

## Tests

TODO: Tests are not yet implemented for this module. When added, run them from the project root:
```bash
./vendor/bin/phpunit modules/ViewLatte/tests
```

## License

This project is licensed under a proprietary license as specified in `composer.json`.
