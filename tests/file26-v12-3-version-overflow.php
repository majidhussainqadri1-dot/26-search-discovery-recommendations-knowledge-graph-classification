<?php
/** FR002/004: unsigned 64-bit strings must not silently cast to PHP_INT_MAX. */
namespace {
    define('ABSPATH',__DIR__.'/');
}
namespace Sabri\File26 {
    require_once dirname(__DIR__).'/includes/class-file26-indexer.php';
}
namespace {
    $obj=(new \ReflectionClass('Sabri\File26\Indexer'))->newInstanceWithoutConstructor();
    $strict=new \ReflectionMethod($obj,'strict_integer');
    $strict->setAccessible(true);
    $check=function($value,$minimum,$expected,$message)use($strict,$obj){
        $result=$strict->invoke($obj,$value,$minimum);
        if($result!==$expected){fwrite(STDERR,"FAIL: $message\n");exit(1);}
        echo "PASS: $message\n";
    };
    $max=PHP_INT_MAX;
    $maxDigits=(string)$max;
    $overflow=($maxDigits==='9223372036854775807')
        ? '9223372036854775808'
        : $maxDigits.'0';
    $check(1,1,1,'Native positive owner object version accepted');
    $check('00000002',1,2,'Leading-zero version normalized without overflow');
    $check(' 00002 ',0,2,'Valid bounded owner sequence tolerates surrounding whitespace');
    $check('0',0,0,'Nonnegative zero sequence accepted where contract permits');
    $check('0',1,null,'Zero object version rejected when minimum is one');
    $check($max,1,$max,'Exact PHP integer maximum accepted safely');
    $check($maxDigits,1,$max,'Exact maximum numeric string accepted safely');
    $check($overflow,1,null,'First out-of-range version rejected, not clamped to PHP_INT_MAX');
    $check('18446744073709551615',1,null,'Unsigned BIGINT maximum rejected until explicit string-arithmetic support');
    $check('999999999999999999999999999999999999',0,null,'Extremely long version cannot collapse into same integer');
    $check('-1',0,null,'Negative owner event refused');
    $check('1e3',0,null,'Scientific-notation owner version refused');
    $check('3.0',0,null,'Floating-point string owner version refused');
    $check(1.0,0,null,'Floats do not become ambiguous owner event integers');
    echo "PASS: FR002 lossless owner version and event-sequence boundary\n";
}
