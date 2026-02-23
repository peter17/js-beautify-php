<?php

require_once __DIR__.'/vendor/autoload.php';

use JSBeautify\JSBeautify;
use PHPUnit\Framework\TestCase;

class JSBeautifyTest extends TestCase
{
    /**
     * Test basic JavaScript beautification with simple variable declaration
     */
    public function testBasicVariableDeclaration()
    {
        $input = 'var x=1;var y=2;';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("var x = 1;\nvar y = 2;", $output);
    }

    /**
     * Test empty input handling
     */
    public function testEmptyInput()
    {
        $beautifier = new JSBeautify('');
        $output = $beautifier->getResult();

        $this->assertSame('', $output);
    }

    /**
     * Test single line comment preservation
     */
    public function testSingleLineComment()
    {
        $input = 'var x=1;//This is a comment';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("var x = 1; //This is a comment\n", $output);
    }

    /**
     * Test multi-line comment preservation
     */
    public function testMultiLineComment()
    {
        $input = 'var x=1;/*This is a\nmulti-line\ncomment*/var y=2;';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("var x = 1;\n/*This is a\\nmulti-line\\ncomment*/\n\nvar y = 2;", $output);
    }

    /**
     * Test string preservation with double quotes
     */
    public function testStringPreservationDoubleQuotes()
    {
        $input = 'var str="Hello World";';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame('var str = "Hello World";', $output);
    }

    /**
     * Test string preservation with single quotes
     */
    public function testStringPreservationSingleQuotes()
    {
        $input = "var str='Hello World';";
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("var str = 'Hello World';", $output);
    }

    /**
     * Test bracket and brace formatting in if statement
     */
    public function testIfStatementFormatting()
    {
        $input = 'if(true){console.log("test");}';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("if (true) {\n    console.log(\"test\");\n}", $output);
    }

    /**
     * Test array formatting
     */
    public function testArrayFormatting()
    {
        $input = 'var arr=[1,2,3,4,5];';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame('var arr = [1, 2, 3, 4, 5];', $output);
    }

    /**
     * Test object literal formatting
     */
    public function testObjectLiteralFormatting()
    {
        $input = 'var obj={name:"test",value:42};';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("var obj = {\n    name: \"test\",\n    value: 42\n};", $output);
    }

    /**
     * Test function declaration formatting
     */
    public function testFunctionDeclaration()
    {
        $input = 'function myFunc(a,b,c){return a+b+c;}';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("function myFunc(a, b, c) {\n    return a + b + c;\n}", $output);
    }

    /**
     * Test for loop formatting
     */
    public function testForLoopFormatting()
    {
        $input = 'for(var i=0;i<10;i++){console.log(i);}';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("for (var i = 0; i < 10; i++) {\n    console.log(i);\n}", $output);
    }

    /**
     * Test while loop formatting
     */
    public function testWhileLoopFormatting()
    {
        $input = 'while(true){break;}';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("while (true) {\n    break;\n}", $output);
    }

    /**
     * Test ternary operator handling
     */
    public function testTernaryOperator()
    {
        $input = 'var x=true?1:2;';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame('var x = true ? 1 : 2;', $output);
    }

    /**
     * Test custom indent size option
     */
    public function testCustomIndentSize()
    {
        $input = 'if(true){var x=1;}';
        $beautifier = new JSBeautify($input, ['indent_size' => 2]);
        $output = $beautifier->getResult();

        $this->assertSame("if (true) {\n  var x = 1;\n}", $output);
    }

    /**
     * Test custom indent character option
     */
    public function testCustomIndentChar()
    {
        $input = 'if(true){var x=1;}';
        $beautifier = new JSBeautify($input, ['indent_char' => "\t"]);
        $output = $beautifier->getResult();

        $this->assertSame("if (true) {\n\t\t\t\tvar x = 1;\n}", $output);
    }

    /**
     * Test complex nested structure
     */
    public function testComplexNestedStructure()
    {
        $input = 'function test(){if(condition){for(var i=0;i<10;i++){if(arr[i]){console.log(arr[i]);}}}}';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("function test() {\n    if (condition) {\n        for (var i = 0; i < 10; i++) {\n            if (arr[i]) {\n                console.log(arr[i]);\n            }\n        }\n    }\n}", $output);
    }

    /**
     * Test that beautifier handles code with script tags
     */
    public function testScriptTagHandling()
    {
        $input = '<script type="text/javascript">var x=1;</script>';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame('<script type="text/javascript">var x = 1;</script>', $output);
    }

    /**
     * Test regex literal preservation
     */
    public function testRegexLiteralPreservation()
    {
        $input = 'var regex=/[a-z]+/g;';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame('var regex = /[a-z]+/g;', $output);
    }

    /**
     * Test switch statement formatting
     */
    public function testSwitchStatementFormatting()
    {
        $input = 'switch(x){case 1:break;case 2:break;default:break;}';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame("switch (x) {\ncase 1:\n    break;\ncase 2:\n    break;\ndefault:\n    break;\n}", $output);
    }

    /**
     * Test method chaining
     */
    public function testMethodChaining()
    {
        $input = 'obj.method1().method2().method3();';
        $beautifier = new JSBeautify($input);
        $output = $beautifier->getResult();

        $this->assertSame('obj.method1().method2().method3();', $output);
    }
}
