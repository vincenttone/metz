<?php
namespace Metz\sys;

define('METZ_RUN_MODE_PRO', 1);
define('METZ_RUN_MODE_DEV', 2);
define('METZ_RUN_MODE_UT', 3);
define('METZ_RUN_MODE_PRE', 4);

class Constant
{
    const VERSION_MAJOR = 1;
    const VERSION_MINOR = 0;
    const VERSION_TINY  = 0;
    const VERSION_CODE  = 'double blade';

    const RUN_MODE_PRO = METZ_RUN_MODE_PRO;  // production
    const RUN_MODE_DEV = METZ_RUN_MODE_DEV;  // development
    const RUN_MODE_UT  = METZ_RUN_MODE_UT;  // unit test
    const RUN_MODE_PRE = METZ_RUN_MODE_PRE;  // pre-production

    static function version()
    {
        return array(self::VERSION_MAJOR, self::VERSION_MINOR, self::VERSION_TINY, self::VERSION_CODE);
    }
    
    static function version_str()
    {
        $version = self::version();
        $code = array_pop($version);
        return implode('.', $version) . ' ['. $code . ']';
    }
}
