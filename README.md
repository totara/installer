# Totara Composer Installer Plugin

Composer installer for Totara-supported plugins.

## Installing

Run `composer install totara/installer` to add this to your project.

## Plugin Structure

The root of your plugin should match a Totara component, for example containing a db or classes folder. At minimum it must have a version.php file.

### Client Code

There is a special .client folder that is moved to the client/{name} location during build. The name of the client component is taken from the tui.json file included inside.
