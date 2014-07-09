## Zend_Log useful Writers & Formatters

#### Requirements
* \Zend_Log (ZF1)

#### Usage

    $writer = new \Terranet\Log\Writer\Growl("App", array(
        'address' => "127.0.0.1",
        'port'    => 9887,
        'password'=> "%password%"
    ));
    $writer->setFormatter(new \Terranet\Log\Formatter\Growl());
    
    $logger = new Zend_Log($writer);
    $logger->log("Debug message", Zend_Log::DEBUG);

#### Installation

###### Via Composer
add a following line (root-only) into your composer.json

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/TerranetMD/log.git"
        }
    ]

run

    composer update

###### Via GitHub

    git clone https://github.com/TerranetMD/log.git
