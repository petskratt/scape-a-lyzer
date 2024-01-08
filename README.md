# scape-a-lyzer

This is a PoC for what I'd like to see in Kubescape HTML output when auditing a K8S setup:

* failed controls:
- problem description
- how it was detected / how to check fix
- remediation as description and sample, references
- list of failing manifests
- ... with easy access to check

Note: code is extremely ad-hoc, written while trying to make up my mind about what I actually want.

## Usage

Run a Kubescape scan in your current context and save output to `.json`:

```
kubescape scan framework cis-v1.23-t1.0.1,allcontrols --format json --output /tmp/scape.json
```

Convert `.json` to `.html`

Enjoy the result!

## Example

Sample output from run on fresh [K3D](https://github.com/k3d-io/k3d]) cluster is [/examples/sample.html](/examples/sample.html).

## Requirements / installation

GIT: [https://github.com/petskratt/scape-a-lyzer](https://github.com/petskratt/scape-a-lyzer)

Requires initialisation of `kubescape/regolibrary` submodule.

Runs on fine PHP 8.3.1 + [YAML extension](https://www.php.net/manual/en/book.yaml.php).