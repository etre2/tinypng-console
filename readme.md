Allows TinyPNG to be executed from the command line for lossless image compression.

Install TinyPNG Console
=

`compose global require etre2/tinypng-console`

Ensure that `$HOME/.composer/vendor/bin directory` is in your $PATH so that the `tinypng` command is available system-wide. This path may very from OS-to-OS.

```
TinyPNG Console 1.0.4

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help             Displays help for a command
  list             Lists commands
  reduce           "Optimize your images with a perfect balance in quality and file size." - TinyPNG
 api-key
  api-key:config   Configure console for API use.
  api-key:request  Request an API Key @ https://tinypng.com/developers
```
