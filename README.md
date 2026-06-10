# Totara Composer Installer Plugin

A Composer plugin that installs Totara plugins into the correct location inside a
Totara codebase.

Totara has a fixed directory layout: a block lives under `server/blocks`, a local
plugin under `server/local`, an authentication plugin under `server/auth`, and so
on. Composer, by default, would drop every dependency into `vendor/`. This plugin
overrides that behaviour so that a package declaring a Totara plugin type is placed
where Totara expects to find it, ready to be detected and upgraded by the Totara
plugin system.

In addition to placing the server-side code, the plugin understands the Totara UI
(TUI) client convention: if a package ships a client component it is moved into the
shared `client/component` tree during installation, and cleaned up again on
uninstall. A lock file (`totara-client.lock`) records what was installed so that
updates and removals can be handled reliably.

## Installing

Add the plugin to the Totara project (the codebase you are installing plugins
into):

```
composer require totara/installer
```

Composer plugins must be allowed to run, so the project's `composer.json` should
also list this plugin under `config.allow-plugins`.

## Declaring a package so it uses this installer

For a package to be handled by this installer it **must** set its `type` in
`composer.json` to `totara-{plugintype}`, where `{plugintype}` is a recognised
Totara plugin type. This `type` value is the single most important field: it is
how the installer recognises the package and decides where the code belongs. A
package without a `totara-` type is ignored and installed by Composer in the
default way.

```json
{
    "name": "totara/block_certificates",
    "type": "totara-block"
}
```

The plugin type maps to a fixed location, with `{name}` substituted (see
[Install location and naming](#install-location-and-naming) below). A few
examples:

| `type`          | Install location                                |
| --------------- | ----------------------------------------------- |
| `totara-block`  | `server/blocks/{name}`                          |
| `totara-local`  | `server/local/{name}`                           |
| `totara-mod`    | `server/mod/{name}`                             |
| `totara-tool`   | `server/admin/tool/{name}`                      |
| `totara-auth`   | `server/auth/{name}`                            |
| `totara-weka`   | `server/lib/editor/weka/extensions/{name}`      |

The full list of supported types and their locations is defined in
[`src/installers/DropInLocations.php`](src/installers/DropInLocations.php). If a
package uses a `totara-` type that is not in this list it will not be installed.

### Install location and naming

The `{name}` segment of the install path is, in order of preference:

1. the value of `extra.installer-name` in the package's `composer.json`, if set; or
2. the package name with its vendor prefix removed (e.g. `totara/block_certificates`
   becomes `block_certificates`).

```json
{
    "name": "totara/weka_ai_assistant",
    "type": "totara-weka",
    "extra": {
        "installer-name": "ai_assistant"
    }
}
```

The example above installs to `server/lib/editor/weka/extensions/ai_assistant`.

## The `.client` directory

A Totara plugin may ship a single TUI client component alongside its server-side
code. This is done by including a `.client` directory in the root of the package.

When a package is installed or updated, the installer looks for a `.client`
directory. If one is present, its contents are placed at:

```
client/component/{name}
```

`{name}` here is taken from the `component` field of the `.client/tui.json` file
(see below). By convention this matches the package name without its vendor prefix,
but the value the installer actually uses is the one declared in `tui.json` — it is
independent of `extra.installer-name`, which only affects the server-side path.

A package may provide **at most one** client component. The `.client` directory is
moved as a whole, so it cannot describe more than a single TUI component.

### Contents of `.client`

The `.client` directory should contain:

- **`tui.json`** — the TUI component manifest. Its `component` field names the
  client component and therefore determines the `client/component/{name}` directory
  the contents are installed into. Without a readable `component` value the client
  component is skipped.
- **`src`** — the component source (Vue components, JS, SCSS).
- **`build`** — the compiled TUI bundle.

For example, a package installed at `server/local/cpd_diary` with a `.client`
directory whose `tui.json` declares `"component": "local_cpd_diary"` results in its
client component being installed to `client/component/local_cpd_diary`.

### Symlinking for development

When a package is installed from source as a symlink (for example with
`composer install --prefer-source` against a path repository), the client
component is symlinked rather than copied, so local edits are picked up
immediately. Forcing a symlink can be opted into per developer with the
`TOTARA_DEV_SYMLINK` environment variable:

```
TOTARA_DEV_SYMLINK=1 composer install --prefer-source
```

## The lock file

Each time a client component is installed, the installer records it in
`totara-client.lock` at the root of the project (the component name, the source
path, and the destination path). This lets the installer remove the correct
`client/component` directory when the package is updated or uninstalled. The file
is managed automatically and should not be edited by hand.
