[![Build Status](https://travis-ci.org/cssjanus/php-cssjanus.svg?branch=master)](https://travis-ci.org/cssjanus/php-cssjanus)

# CSSJanus

Convert CSS stylesheets between left-to-right and right-to-left. This is a PHP port of [CSSJanus](https://code.google.com/p/cssjanus/) (written in Python).

## Basic usage
```php
$rtlCss = CSSJanus::transform( $ltrCss );
```

## Advanced usage

``transform( $css, $swapLtrRtlInURL = false, $swapLeftRightInURL = false )``

* ``$css`` (string) Stylesheet to transform
* ``$swapLtrRtlInURL`` (boolean) Swap 'ltr' and 'rtl' in URLs
* ``$swapLeftRightInURL`` (boolean) Swap 'left' and 'right' in URLs

### Preventing flipping
Use a ```/* @noflip */``` comment to protect a rule from being changed.

```css
.rule1 {
  /* Will be converted to margin-right */
  margin-left: 1em;
}
/* @noflip */
.rule2 {
  /* Will be preserved as margin-left */
  margin-left: 1em;
}
```

## Additional Resources
* [node-cssjanus](https://github.com/cssjanus/node-cssjanus)
