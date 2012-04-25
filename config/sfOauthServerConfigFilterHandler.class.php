<?php
/*
  *  This file is part of the sfOauthServerPlugin package.
 * (c) Jean-Baptiste Cayrou <lordartis@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *  
 * This code is in part extracted and adapted from sfFilterConfigHandler file of Symfony
 * Thank to Fabien Potencier and Sean Kerr for their work.
 * 
 * It actives the sfOauthFilter just if the action has in its oauth.yml config file : is_secure : true
 * In fact it works exactly like the security config system 
 * @see sfSecurityConfigHandler
 * */
class sfOauthServerConfigFilterHandler extends sfFilterConfigHandler
{
	
  /**
   * Executes this configuration handler
   *
   * @param array $configFiles An array of absolute filesystem path to a configuration file
   *
   * @return string Data to be written to a cache file
   *
   * @throws sfConfigurationException If a requested configuration file does not exist or is not readable
   * @throws sfParseException If a requested configuration file is improperly formatted
   */
  public function execute($configFiles)
  {
    // parse the yaml
    $config = self::getConfiguration($configFiles);

    // init our data and includes arrays
    $data     = array();
    $includes = array();

    $execution = false;
    $rendering = false;

    // let's do our fancy work
    foreach ($config as $category => $keys)
    {
      if (isset($keys['enabled']) && !$keys['enabled'])
      {
        continue;
      }

      if (!isset($keys['class']))
      {
        // missing class key
        throw new sfParseException(sprintf('Configuration file "%s" specifies category "%s" with missing class key.', $configFiles[0], $category));
      }

      $class = $keys['class'];

      if (isset($keys['file']))
      {
        if (!is_readable($keys['file']))
        {
          // filter file doesn't exist
          throw new sfParseException(sprintf('Configuration file "%s" specifies class "%s" with nonexistent or unreadable file "%s".', $configFiles[0], $class, $keys['file']));
        }

        // append our data
        $includes[] = sprintf("require_once('%s');\n", $keys['file']);
      }

      $condition = true;
      if (isset($keys['param']['condition']))
      {
        $condition = $keys['param']['condition'];
        unset($keys['param']['condition']);
      }

      $type = isset($keys['param']['type']) ? $keys['param']['type'] : null;
      unset($keys['param']['type']);

      if ($condition)
      {
        // parse parameters
        $parameters = isset($keys['param']) ? var_export($keys['param'], true) : 'null';

        // append new data
        if ('security' == $type)
        {
          $data[] = $this->addSecurityFilter($category, $class, $parameters);
        }
        elseif ('oauth' == $type)
        {
		  $data[] = $this->addOauthSecurityFilter($category, $class, $parameters);
		}
        else
        {
          $data[] = $this->addFilter($category, $class, $parameters);
        }
        
        if ('rendering' == $type)
        {
          $rendering = true;
        }

        if ('execution' == $type)
        {
          $execution = true;
        }
      }
    }

    if (!$rendering)
    {
      throw new sfParseException(sprintf('Configuration file "%s" must register a filter of type "rendering".', $configFiles[0]));
    }

    if (!$execution)
    {
      throw new sfParseException(sprintf('Configuration file "%s" must register a filter of type "execution".', $configFiles[0]));
    }

    // compile data
    $retval = sprintf("<?php\n".
                      "// auto-generated by sfOauthServerConfigFilterHandler\n".
                      "// date: %s\n%s\n%s\n\n", date('Y/m/d H:i:s'),
                      implode("\n", $includes), implode("\n", $data));

    return $retval;
  }
  
	
 /**
   * Adds a Oauth security filter statement to the data.
   *
   * @param string $category   The category name
   * @param string $class      The filter class name
   * @param array  $parameters Filter default parameters
   *
   * @return string The PHP statement
   */
  protected function addOauthSecurityFilter($category, $class, $parameters)
  {
    return <<<EOF

// does this action require oauth authenticated ?
\$sfOauth= new sfOauthServerBase(sfContext::getInstance(),\$actionInstance->getModuleName(),\$actionInstance->getActionName());
if (\$sfOauth->isOauthSecure())
{
\$sfOauth->connectEvent();
  {$this->addFilter($category, $class, $parameters)}
}
EOF;
  }
  
}
