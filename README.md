# Beacon

[![Beacon](https://apisnetworks.com/images/beacon/beacon.png)](https://github.com/apisnetworks/beacon)

Beacon provides a command-line interface to [apnscp](https://github.com/apisnetworks/apnscp-modules) API + module introspection.

## Usage
### Prerequisites
Beacon requires a key to be setup first in the control panel. Visit **Dev** > **API Keys** to generate a key. Beacon also requires at least PHP7, which restricts operation to [v6.5+ platforms](https://kb.hostineer.com/platform/determining-platform-version/). Set the key by running exec with --key. Overwrite a previously configured key with -s:
```bash 
beacon exec --key=somekey -s common_get_uptime
```

### Commands
#### show
**show** *service*
*Display underlying code for given *service**

**Example**
```bash
beacon show common_get_load
```
**Example response**
```php 
/**
 * array get_load (void)
 *
 * @privilege PRIVILEGE_ALL
 * @return array returns an assoc array of the 1, 5, and 15 minute
 * load averages; indicies of 1,5,15
 */
 public function get_load()
 {
     $fp = fopen('/proc/loadavg', 'r');
     $loadData = fgets($fp);
     fclose($fp);
     $loadData = array_slice(explode(" ", $loadData), 0, 3);
     return array_combine(array(1, 5, 15), $loadData);
 }
```

#### exec
**exec** *flags* *service* [*args*, ...]
*Executes named service with optional *args**

**Example**
```bash
beacon exec common_get_uptime
```
**Example response**
```bash
25 days 10 mins
```

##### Optional flags
- **format** [json, bash, php]
*Alter output format*
```bash
beacon exec --format=json common_get_load
```
```json
{"1":"0.00","5":"0.00","15":"0.00"}
```

```bash
beacon exec --format=php common_get_load
```

```
Array
(
    [1] => 0.04
    [5] => 0.01
    [15] => 0.00
)
```

```bash
beacon exec --format=bash common_get_load
```
```bash
(["1"]="0.04" ["5"]="0.01" ["15"]="0.00")
```
Bash formatting can be used in shell scripting to populate variables, e.g.
```bash
declare -a load=`beacon exec --format=bash common_get_load`
echo ${load[1]}
```

- **set**
Set API key as default on exit

- **key** *key*
Specify an API key, *key*

- **keyfile** *file*
Specify a file, *file* that contains the API key to use. The file should be formatted as empty consisting of nothing but the key.

- **endpoint** *url*
Use the endpoint *url* instead of http://localhost:2082/soap.

## arrays and hashes
Arrays and hashes are fed using a bracketed expression [] which may also be nested.
```bash
beacon e file_delete '[/tmp/a, /tmp/b]'
```

```bash
beacon e user_add_user "newuser" "newpassword" "some new user" '[imap:1,smtp:1]'
```

## null and bool types
null and false may be passed verbatim to indicate a null or true/false parameter. To pass a string literal of the corresponding type, surround  the argument with both single and double quotes,

**Clear ACLs for user myuser**

```bash
beacon e file_set_acls /home/foo myuser null
```
This is interchangeable, due to shell parsing mechanics, to,
```bash
beacon e file_set_acls /home/foo myuser "null"
```

**Set read, write, execute for user "null"**

```bash
beacon e file_set_acls /home/foo "'null'" 7
```

## API reference

All modules are available through [api.apnscp.com](https://api.apnscp.com/namespace-none.html). Modules that follow the naming `XXX_Module` are exposed as `xxx`, for example methods in `File_Module` are exposed as `file_<method>` and [`set_acls`](https://api.apnscp.com/class-File_Module.html#_set_acls) in `File_Module` is referenced as `file_set_acls`.
