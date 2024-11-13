<?php

declare(strict_types=1);

namespace LaminasTest\Form\View\Helper;

use Laminas\Form\Element;
use Laminas\Form\Exception\DomainException;
use Laminas\Form\View\Helper\FormFile as FormFileHelper;

use function sprintf;

/**
 * @property FormFileHelper $helper
 */
final class FormFileTest extends AbstractCommonTestCase
{
    protected function setUp(): void
    {
        $this->helper = new FormFileHelper();
        parent::setUp();
    }

    /**
     * @return void
     */
    public function testRaisesExceptionWhenNameIsNotPresentInElement()
    {
        $element = new Element\File();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('name');
        $this->helper->render($element);
    }

    /**
     * @return void
     */
    public function testGeneratesFileInputTagWithElement()
    {
        $element = new Element\File('foo');
        $markup  = $this->helper->render($element);
        self::assertStringContainsString('<input ', $markup);
        self::assertStringContainsString('type="file"', $markup);
    }

    /**
     * @return void
     */
    public function testGeneratesFileInputTagRegardlessOfElementType()
    {
        $element = new Element\File('foo');
        $element->setAttribute('type', 'email');
        $markup = $this->helper->render($element);
        self::assertStringContainsString('<input ', $markup);
        self::assertStringContainsString('type="file"', $markup);
    }

    /**
     * @return void
     */
    public function testRendersElementWithFileIgnoresValue()
    {
        $element = new Element\File('foo');
        $element->setValue([
            'tmp_name' => '/tmp/foofile',
            'name'     => 'foofile',
            'type'     => 'text',
            'size'     => 200,
            'error'    => 2,
        ]);
        $markup = $this->helper->render($element);
        self::assertStringContainsString('<input ', $markup);
        self::assertStringContainsString('type="file"', $markup);
        self::assertStringNotContainsString('value="', $markup);
    }

    public static function validAttributes(): array
    {
        return [
            ['name', 'assertStringContainsString'],
            ['accept', 'assertStringContainsString'],
            ['alt', 'assertStringNotContainsString'],
            ['autocomplete', 'assertStringNotContainsString'],
            ['autofocus', 'assertStringContainsString'],
            ['checked', 'assertStringNotContainsString'],
            ['dirname', 'assertStringNotContainsString'],
            ['disabled', 'assertStringContainsString'],
            ['form', 'assertStringContainsString'],
            ['formaction', 'assertStringNotContainsString'],
            ['formenctype', 'assertStringNotContainsString'],
            ['formmethod', 'assertStringNotContainsString'],
            ['formnovalidate', 'assertStringNotContainsString'],
            ['formtarget', 'assertStringNotContainsString'],
            ['height', 'assertStringNotContainsString'],
            ['list', 'assertStringNotContainsString'],
            ['max', 'assertStringNotContainsString'],
            ['maxlength', 'assertStringNotContainsString'],
            ['min', 'assertStringNotContainsString'],
            ['multiple', 'assertStringNotContainsString'],
            ['pattern', 'assertStringNotContainsString'],
            ['placeholder', 'assertStringNotContainsString'],
            ['readonly', 'assertStringNotContainsString'],
            ['required', 'assertStringContainsString'],
            ['size', 'assertStringNotContainsString'],
            ['src', 'assertStringNotContainsString'],
            ['step', 'assertStringNotContainsString'],
            ['width', 'assertStringNotContainsString'],
        ];
    }

    /**
     * @return Element\File
     */
    public function getCompleteElement(): Element
    {
        $element = new Element\File('foo');
        $element->setAttributes([
            'accept'         => 'value',
            'alt'            => 'value',
            'autocomplete'   => 'on',
            'autofocus'      => 'autofocus',
            'checked'        => 'checked',
            'dirname'        => 'value',
            'disabled'       => 'disabled',
            'form'           => 'value',
            'formaction'     => 'value',
            'formenctype'    => 'value',
            'formmethod'     => 'value',
            'formnovalidate' => 'value',
            'formtarget'     => 'value',
            'height'         => 'value',
            'id'             => 'value',
            'list'           => 'value',
            'max'            => 'value',
            'maxlength'      => 'value',
            'min'            => 'value',
            'multiple'       => false,
            'name'           => 'value',
            'pattern'        => 'value',
            'placeholder'    => 'value',
            'readonly'       => 'readonly',
            'required'       => 'required',
            'size'           => 'value',
            'src'            => 'value',
            'step'           => 'value',
            'width'          => 'value',
        ]);
        $element->setValue('value');
        return $element;
    }

    /**
     * @dataProvider validAttributes
     */
    public function testAllValidFormMarkupAttributesPresentInElementAreRendered(
        string $attribute,
        string $assertion
    ): void {
        $element = $this->getCompleteElement();
        $markup  = $this->helper->render($element);
        $expect  = match ($attribute) {
            'value' => sprintf('%s="%s"', $attribute, (string) $element->getValue()),
            default => sprintf('%s="%s"', $attribute, (string) $element->getAttribute($attribute)),
        };
        $this->$assertion($expect, $markup);
    }

    /**
     * @return void
     */
    public function testNameShouldHaveArrayNotationWhenMultipleIsSpecified()
    {
        $element = new Element\File('foo');
        $element->setAttribute('multiple', true);
        $markup = $this->helper->render($element);
        self::assertMatchesRegularExpression('#<input[^>]*?(name="foo\&\#x5B\;\&\#x5D\;")#', $markup);
    }

    /**
     * @return void
     */
    public function testInvokeProxiesToRender()
    {
        $element = new Element\File('foo');
        $markup  = $this->helper->__invoke($element);
        self::assertStringContainsString('<input', $markup);
        self::assertStringContainsString('name="foo"', $markup);
        self::assertStringContainsString('type="file"', $markup);
    }

    /**
     * @return void
     */
    public function testInvokeWithNoElementChainsHelper()
    {
        self::assertSame($this->helper, $this->helper->__invoke());
    }
}
