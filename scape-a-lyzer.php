<?php
/*
PoC for what I'd like to see in Kubescape HTML output
peeter.marvet@vaimo.com / 2023-01-08

Usage:
    Save `kubescape` output to .json
    kubescape scan framework cis-v1.23-t1.0.1,allcontrols --format json --output /tmp/scape.json

    Create `.html` from `.json`
    php -d memory_limit=1024M scape-a-lyzer.php /tmp/scape.json > /tmp/scape.html
*/


if (empty($argv[1]) || !is_readable($argv[1]) || !is_file($argv[1])) {
    echo 'Please provide name of Kubescape output .json as first parameter!' . PHP_EOL;
    die();
}

$scape = json_decode(file_get_contents($argv[1]), true);

$resources = [];

foreach ($scape['resources'] as $resource) {
    $resources[$resource['resourceID']] = $resource['object'];
}

$control_descriptions = [];

foreach (glob(__DIR__ . '/regolibrary/controls/*.json') as $filename) {
    $json = json_decode(file_get_contents($filename), true);
    $control_descriptions[$json['controlID']] = $json;
}

$controls = [];
$control_names = [];
$control_paths = [];

foreach ($scape['results'] as $resource) {
    foreach ($resource['controls'] as $control) {
        if (!in_array($control['status']['status'], ['passed', 'skipped'])) {

            $control_id = $control['controlID'];

            // control names in regolibrary that we use later do not provide CIS ID prefix
            if (empty($control_names[$control_id])) {
                $control_names[$control_id] = $control['name'];
            }

            $obj = $resources[$resource['resourceID']];

            if(!empty($control['rules'])) {
                foreach ($control['rules'] as $rule) {
                    if (!in_array($rule['status'], ['passed', 'skipped'])) {
                        if (!empty($rule['paths'])) {

                            if (empty($control_paths[$control_id])) {
                                $control_paths[$control_id] = [];
                            }

                            foreach ($rule['paths'] as $path) {

                                if (!empty($path['failedPath'])) {
                                    $add_path = preg_replace('/(\[\d+\])/', '[*]', $path['failedPath']);
                                    if (!in_array($add_path, $control_paths[$control_id], true)) {
                                        $control_paths[$control_id][] =$add_path;
                                    }
                                }

                                if (!empty($path['reviewPath'])) {
                                    $add_path = preg_replace('/(\[\d+\])/', '[*]', $path['reviewPath']);
                                    if (!in_array($add_path, $control_paths[$control_id], true)) {
                                        $control_paths[$control_id][] =$add_path;
                                    }
                                }

                                if (!empty($path['fixPath']) && !empty($path['fixPath']['path'])) {
                                    $add_path = preg_replace('/(\[\d+\])/', '[*]', $path['fixPath']['path']) .' = ' . $path['fixPath']['value'];
                                    if (!in_array($add_path, $control_paths[$control_id], true)) {
                                        $control_paths[$control_id][] =$add_path;
                                    }
                                }
                            }
                        }
                    }
                }

            }

            if (!empty($obj['name'])) {
                $name = $obj['name'];
            } elseif (!empty($obj['metadata']['name'])) {
                $name = $obj['metadata']['name'];
            }

            if (!empty($obj['namespace'])) {
                $namespace = $obj['namespace'];
            } elseif (!empty($obj['metadata']['namespace'])) {
                $namespace = $obj['metadata']['namespace'];
            } else {
                $namespace = "-cluster-";
            }

            $fullname = "{$namespace} {$obj['kind']} {$name}";

            if (empty($controls[$control_id])) {
                $controls[$control_id] = [];
            }

            $controls[$control_id][$fullname] = $obj;

        }
    }
}

ksort($controls);

$itemcounter = 0;

$accordion = '';

if (!empty($scape['clusterName'])) {
    $accordion .= "<h1>Cluster: {$scape['clusterName']}</h1>";
} else {
    $accordion .= "<h1>Context: {$scape['metadata']['targetMetadata']['clusterContextMetadata']['contextName']}</h1>";
}

$accordion .= "<p>K8S version {$scape['clusterAPIServerInfo']['gitVersion']}, built on {$scape['clusterAPIServerInfo']['buildDate']}, {$scape['metadata']['targetMetadata']['clusterContextMetadata']['numberOfWorkerNodes']} nodes</p>";
$accordion .= "<p>Kubescape version {$scape['metadata']['scanMetadata']['kubescapeVersion']}</p>";
$accordion .= "<p>Frameworks: " . implode(', ', $scape['metadata']['scanMetadata']['targetNames']) . "</p>";


foreach ($controls as $control_id => $findings) {

    $itemcounter++;

    ksort($findings);

    $accordion .= "<div class='accordion-item'>";

    // start header
    $accordion .= "<div class='accordion-header' id='control_{$itemcounter}_header'>";
    $accordion .= "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#control_$itemcounter' aria-expanded='false' aria-controls='control_$itemcounter'>";
    $accordion .= "<div class='row'><div class='col'>";
    $accordion .= "<h2 class='fs-4'>{$control_id}: {$control_names[$control_id]}</h2>";
    $accordion .= "<p class='mb-0'> {$control_descriptions[$control_id]['description']}</p>";
    $accordion .= "</div></div>";
    $accordion .= "</button>";
    $accordion .= "</div>";
    // end header

    // start body
//    $accordion .= "<div id='collapse_$itemcounter' class='accordion-collapse collapse' aria-labelledby='heading_$itemcounter' data-bs-parent='#report'>";
    $accordion .= "<div id='control_$itemcounter' class='accordion-collapse collapse' aria-labelledby='control_{$itemcounter}_header' data-bs-parent='#report'>";

    $accordion .= "<div class='accordion-body'>";

    // start content
    if (!empty($control_descriptions[$control_id]['long_description']))
        $accordion .= "<p>{$control_descriptions[$control_id]['long_description']}</p>";

    if (!empty($control_descriptions[$control_id]['test'])) {
        $accordion .= "<h5>Detection</h5>";
        $accordion .= "<p>{$control_descriptions[$control_id]['test']}</p>";
    }

    if (!empty($control_descriptions[$control_id]['remediation'])) {
        $accordion .= "<h5>Remediation</h5>";
        $accordion .= "<p>{$control_descriptions[$control_id]['remediation']}</p>";
    }


    if (!empty($control_paths[$control_id])) {
        $accordion .= "<h5>Checked paths and expected values</h5>";
        $accordion .= '<ul>';
        asort($control_paths[$control_id]);
        foreach ($control_paths[$control_id] as $path) {
            $accordion .= "<li>{$path}</li>";
        }
        $accordion .= '</ul>';
    }

    if (!empty($control_descriptions[$control_id]['example'])) {
        $accordion .= "<h5>Example</h5>";
        if (stripos($control_descriptions[$control_id]['example'], '@') === 0) {
            $example = file_get_contents(str_replace('@', __DIR__ . '/regolibrary/', $control_descriptions[$control_id]['example']));
        } else {
            $example = $control_descriptions[$control_id]['example'];
        }
        $accordion .= "<pre>$example</pre>";
    }

    if (!empty($control_descriptions[$control_id]['references'])) {
        $accordion .= "<h5>References</h5>";
        $accordion .= '<ul>';
        foreach ($control_descriptions[$control_id]['references'] as $reference) {
            $accordion .= "<li><a href='{$reference}'>{$reference}</a></li>";
        }
        $accordion .= '</ul>';
    }

    $accordion .= "<h5>Affected resources</h5>";
    $accordion .= "<ul>";

    foreach ($findings as $fullname => $object) {
        $accordion .= "<li class='expandable detail-hide'>";
        $accordion .= "{$fullname}";

//        $accordion .= "<div class='details'><pre>" . json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</pre></div>";
//        $accordion .= "<div class='details'><pre>" . Yaml::dump($object, 0) . "</pre></div>";
        $accordion .= "<div class='details'><pre>" . yaml_emit($object) . "</pre></div>";
        $accordion .= "</li>";
    }
    $accordion .= "</ul>";
    // end content

    $accordion .= "</div>";
    $accordion .= "</div>";
    // end body

    $accordion .= "</div>";
    // end item

}


?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>scape-a-lyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <style>
        li.expandable:hover {
            background-color: #f8f9fa;
        }

        li.expandable:hover::after {
            content: none;
        }

        li.expandable.detail-hide:hover::after {
            content: " ›";
        }

        li.expandable.detail-hide .details {
            display: none;
        }

        .collapsing {
            transition: none;
        }


    </style>
</head>
<body>

<div class='container'>
    <div class='row'>
        <div class='col'>
            <div class='accordion accordion-flush' id='report'>
                <?php echo $accordion; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
<script>
    // show details when resource is clicked
    const expandables = document.querySelectorAll('.expandable');
    expandables.forEach(el => el.addEventListener('click', handleClick));

    function handleClick(e) {
        e.target.classList.toggle('detail-hide');
    }

    // scroll to opened heading
    document.addEventListener('shown.bs.collapse', function(event){
        window.location.href = '#' + event.target.id  + "_header";
        //document.querySelector('#'+ event.target.id + "_header").scrollIntoView({ behavior: 'instant' });

    });

</script>
</body>
</html>