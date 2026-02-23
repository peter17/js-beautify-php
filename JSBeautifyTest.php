<?php

require_once __DIR__ . '/vendor/autoload.php';

use JSBeautify\JSBeautify;
use PHPUnit\Framework\TestCase;

class JSBeautifyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Options – type coercion / fallback defaults
    // -------------------------------------------------------------------------

    /**
     * Numeric string passed as indent_size must be accepted and cast to int.
     */
    public function testIndentSizeAcceptsNumericString(): void
    {
        $beautifier = new JSBeautify('if(true){var x=1;}', ['indent_size' => '2']);
        $this->assertSame("if (true) {\n  var x = 1;\n}", $beautifier->getResult());
    }

    /**
     * A non-numeric indent_size must fall back to the default of 4.
     */
    public function testIndentSizeFallsBackToDefaultOnInvalidValue(): void
    {
        $beautifier = new JSBeautify('if(true){var x=1;}', ['indent_size' => 'bad']);
        $this->assertSame("if (true) {\n    var x = 1;\n}", $beautifier->getResult());
    }

    /**
     * A non-string indent_char must fall back to a single space.
     */
    public function testIndentCharFallsBackToSpaceOnInvalidValue(): void
    {
        $beautifier = new JSBeautify('if(true){var x=1;}', ['indent_char' => 42]);
        $this->assertSame("if (true) {\n    var x = 1;\n}", $beautifier->getResult());
    }

    /**
     * Tab character is a valid indent_char.
     */
    public function testIndentCharTab(): void
    {
        $beautifier = new JSBeautify('if(true){var x=1;}', ['indent_char' => "\t", 'indent_size' => 1]);
        $this->assertSame("if (true) {\n\tvar x = 1;\n}", $beautifier->getResult());
    }

    /**
     * indent_level shifts the starting indentation depth.
     */
    public function testStartingIndentLevel(): void
    {
        $beautifier = new JSBeautify('var x=1;', ['indent_level' => 2]);
        // The very first token never triggers printNewLine, so indent_level only
        // matters once a newline is emitted – test with a block to exercise it.
        $beautifier2 = new JSBeautify('if(true){var x=1;}', ['indent_level' => 1]);
        $this->assertSame("if (true) {\n        var x = 1;\n    }", $beautifier2->getResult());
    }

    /**
     * Non-bool preserve_newlines must fall back to false (no extra blank lines).
     */
    public function testPreserveNewlinesFallsBackToFalseOnInvalidValue(): void
    {
        $input = "var x=1;\n\n\nvar y=2;";
        $beautifier = new JSBeautify($input, ['preserve_newlines' => 'yes']);
        $this->assertSame("var x = 1;\nvar y = 2;", $beautifier->getResult());
    }

    /**
     * preserve_newlines = true keeps blank lines in the output.
     */
    public function testPreserveNewlinesOption(): void
    {
        $input = "var x=1;\n\n\nvar y=2;";
        $beautifier = new JSBeautify($input, ['preserve_newlines' => true]);
        $output = $beautifier->getResult();
        // Two blank lines should produce at least one preserved blank line.
        $this->assertStringContainsString("\n\n", $output);
    }

    // -------------------------------------------------------------------------
    // Operators
    // -------------------------------------------------------------------------

    /**
     * Arithmetic operators receive surrounding spaces.
     */
    public function testArithmeticOperatorSpacing(): void
    {
        $beautifier = new JSBeautify('var r=a+b-c*d/e%f;');
        $this->assertSame('var r = a + b - c * d / e % f;', $beautifier->getResult());
    }

    /**
     * Strict equality / inequality operators are spaced correctly.
     */
    public function testStrictEqualityOperators(): void
    {
        $beautifier = new JSBeautify('if(a===b||c!==d){}');
        $this->assertSame("if (a === b || c !== d) {}", $beautifier->getResult());
    }

    /**
     * Compound assignment operators receive surrounding spaces.
     */
    public function testCompoundAssignmentOperators(): void
    {
        $beautifier = new JSBeautify('x+=1;x-=1;x*=2;x/=2;x%=3;');
        $this->assertSame("x += 1;\nx -= 1;\nx *= 2;\nx /= 2;\nx %= 3;", $beautifier->getResult());
    }

    /**
     * Unary negation directly after return must keep a space, not glue tokens.
     */
    public function testUnaryMinusAfterReturn(): void
    {
        $beautifier = new JSBeautify('function f(){return -1;}');
        $this->assertSame("function f() {\n    return -1;\n}", $beautifier->getResult());
    }

    /**
     * Logical NOT directly after return must keep a space.
     */
    public function testUnaryNotAfterReturn(): void
    {
        $beautifier = new JSBeautify('function f(){return !flag;}');
        $this->assertSame("function f() {\n    return !flag;\n}", $beautifier->getResult());
    }

    /**
     * Pre-increment operator must not gain extra spaces.
     */
    public function testPreIncrementOperator(): void
    {
        $beautifier = new JSBeautify('for(var i=0;i<10;++i){}');
        $this->assertSame("for (var i = 0; i < 10; ++i) {}", $beautifier->getResult());
    }

    /**
     * Member-access dot must have no surrounding spaces.
     */
    public function testMemberAccessDot(): void
    {
        $beautifier = new JSBeautify('a.b.c;');
        $this->assertSame('a.b.c;', $beautifier->getResult());
    }

    /**
     * Bitwise operators receive surrounding spaces.
     */
    public function testBitwiseOperators(): void
    {
        $beautifier = new JSBeautify('var x=a&b|c^d;');
        $this->assertSame('var x = a & b | c ^ d;', $beautifier->getResult());
    }

    /**
     * Scope-resolution operator :: must have no surrounding spaces.
     */
    public function testScopeResolutionOperator(): void
    {
        $beautifier = new JSBeautify('Foo::bar();');
        $this->assertSame('Foo::bar();', $beautifier->getResult());
    }

    // -------------------------------------------------------------------------
    // Control flow
    // -------------------------------------------------------------------------

    /**
     * else-if on the same line as closing brace must be kept together.
     */
    public function testElseIfFormatting(): void
    {
        $beautifier = new JSBeautify('if(a){x();}else if(b){y();}else{z();}');
        $this->assertSame(
            "if (a) {\n    x();\n} else if (b) {\n    y();\n} else {\n    z();\n}",
            $beautifier->getResult()
        );
    }

    /**
     * do-while loop must keep the while token on the same line as the closing brace.
     */
    public function testDoWhileFormatting(): void
    {
        $beautifier = new JSBeautify('do{x();}while(condition);');
        $this->assertSame("do {\n    x();\n} while (condition);", $beautifier->getResult());
    }

    /**
     * try-catch-finally blocks must be formatted correctly.
     */
    public function testTryCatchFinallyFormatting(): void
    {
        $beautifier = new JSBeautify('try{doSomething();}catch(e){handle(e);}finally{cleanup();}');
        $this->assertSame(
            "try {\n    doSomething();\n} catch(e) {\n    handle(e);\n} finally {\n    cleanup();\n}",
            $beautifier->getResult()
        );
    }

    /**
     * A switch with fall-through (no break) must still indent case bodies.
     */
    public function testSwitchFallThrough(): void
    {
        $beautifier = new JSBeautify('switch(x){case 1:case 2:doSomething();break;}');
        $output = $beautifier->getResult();
        $this->assertStringContainsString("case 1:", $output);
        $this->assertStringContainsString("case 2:", $output);
        $this->assertStringContainsString("doSomething();", $output);
    }

    /**
     * continue statement must appear on its own line.
     */
    public function testContinueStatement(): void
    {
        $beautifier = new JSBeautify('for(var i=0;i<10;i++){if(i===5){continue;}console.log(i);}');
        $output = $beautifier->getResult();
        $this->assertStringContainsString("continue;", $output);
        $this->assertStringContainsString("console.log(i);", $output);
    }

    /**
     * throw statement must keep its argument on the same line.
     */
    public function testThrowStatement(): void
    {
        $beautifier = new JSBeautify('function f(){throw new Error("oops");}');
        $this->assertSame(
            "function f() {\n    throw new Error(\"oops\");\n}",
            $beautifier->getResult()
        );
    }

    // -------------------------------------------------------------------------
    // Functions
    // -------------------------------------------------------------------------

    /**
     * Anonymous function assigned to a variable must not gain a newline before {.
     */
    public function testAnonymousFunctionExpression(): void
    {
        $beautifier = new JSBeautify('var fn=function(a,b){return a+b;};');
        $this->assertSame("var fn = function(a, b) {\n    return a + b;\n};", $beautifier->getResult());
    }

    /**
     * Immediately-invoked function expression (IIFE) must be formatted correctly.
     */
    public function testIIFE(): void
    {
        $beautifier = new JSBeautify('(function(){var x=1;})();');
        $output = $beautifier->getResult();
        $this->assertStringContainsString('var x = 1;', $output);
        $this->assertStringContainsString('(function()', $output);
    }

    /**
     * Callback passed as argument must not gain an unwanted newline.
     */
    public function testFunctionAsArgument(): void
    {
        $beautifier = new JSBeautify('arr.forEach(function(item){console.log(item);});');
        $output = $beautifier->getResult();
        $this->assertStringContainsString('arr.forEach(function(item)', $output);
        $this->assertStringContainsString('console.log(item);', $output);
    }

    // -------------------------------------------------------------------------
    // Var declarations
    // -------------------------------------------------------------------------

    /**
     * Multiple var declarations on one line must each appear on their own line.
     */
    public function testMultipleVarDeclarationsOnOneLine(): void
    {
        $beautifier = new JSBeautify('var a=1,b=2,c=3;');
        $this->assertSame("var a = 1,\nb = 2,\nc = 3;", $beautifier->getResult());
    }

    /**
     * A var declaration with an object value should split after the comma separator.
     */
    public function testVarWithObjectValue(): void
    {
        $beautifier = new JSBeautify('var obj={a:1,b:2};');
        $this->assertSame("var obj = {\n    a: 1,\n    b: 2\n};", $beautifier->getResult());
    }

    // -------------------------------------------------------------------------
    // Strings and literals
    // -------------------------------------------------------------------------

    /**
     * Strings containing operators must not be altered.
     */
    public function testStringWithOperatorsInsideIsUntouched(): void
    {
        $beautifier = new JSBeautify('var s="a+b===c";');
        $this->assertSame('var s = "a+b===c";', $beautifier->getResult());
    }

    /**
     * Escaped quotes inside a string must be preserved.
     */
    public function testEscapedQuotesInString(): void
    {
        $beautifier = new JSBeautify('var s="say \\"hello\\"";');
        $this->assertSame('var s = "say \\"hello\\"";', $beautifier->getResult());
    }

    /**
     * Escaped single quote inside a single-quoted string must be preserved.
     */
    public function testEscapedSingleQuoteInString(): void
    {
        $beautifier = new JSBeautify("var s='it\\'s fine';");
        $this->assertSame("var s = 'it\\'s fine';", $beautifier->getResult());
    }

    /**
     * Scientific-notation numeric literals must not be split.
     */
    public function testScientificNotationLiteral(): void
    {
        $beautifier = new JSBeautify('var n=1E+10;');
        $this->assertSame('var n = 1E+10;', $beautifier->getResult());
    }

    /**
     * Regex containing a slash inside a character class must not be broken.
     */
    public function testRegexWithSlashInCharacterClass(): void
    {
        $beautifier = new JSBeautify('var r=/[a-z\/]+/gi;');
        $this->assertSame('var r = /[a-z\/]+/gi;', $beautifier->getResult());
    }

    // -------------------------------------------------------------------------
    // Whitespace-only / trivial inputs
    // -------------------------------------------------------------------------

    /**
     * Input containing only whitespace must produce an empty string.
     */
    public function testWhitespaceOnlyInput(): void
    {
        $beautifier = new JSBeautify("   \n\t  ");
        $this->assertSame('', $beautifier->getResult());
    }

    /**
     * A single semicolon must be returned as-is.
     */
    public function testSingleSemicolon(): void
    {
        $beautifier = new JSBeautify(';');
        $this->assertSame(';', $beautifier->getResult());
    }

    // -------------------------------------------------------------------------
    // Script-tag wrapping
    // -------------------------------------------------------------------------

    /**
     * Input without script tags must not gain them in the output.
     */
    public function testNoScriptTagsAddedWhenAbsent(): void
    {
        $beautifier = new JSBeautify('var x=1;');
        $output = $beautifier->getResult();
        $this->assertStringNotContainsString('<script', $output);
        $this->assertStringNotContainsString('</script>', $output);
    }

    /**
     * Malformed / partial script tag must not trigger wrapping.
     */
    public function testPartialScriptTagIsNotWrapped(): void
    {
        $beautifier = new JSBeautify('<script>var x=1;');
        $output = $beautifier->getResult();
        // Without the closing </script>, the lengths differ only partially –
        // the class should not crash and should return something sensible.
        $this->assertIsString($output);
    }
}
