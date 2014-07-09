## Paginator adapters

#### Requirements
* \Zend_Paginator (ZF1)

#### Usage

    $paginator = new \Terranet\Paginator($items, $page, $perPage, '\Terranet\Paginator\Adapter\MongoCursor');

#### Installation

###### Via Composer
add a following line (root-only) into your composer.json

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/TerranetMD/paginator.git"
        }
    ]

run

    composer update

###### Via GitHub

    git clone https://github.com/TerranetMD/paginator.git
