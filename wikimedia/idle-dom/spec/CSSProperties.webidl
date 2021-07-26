// In https://drafts.csswg.org/cssom/#the-cssstyledeclaration-interface
// (sorry, there's not a better anchor, see
// https://github.com/w3c/csswg-drafts/issues/6421)
// we read that there should be special getters/setters on the
// CSSStyleDeclaration interface corrsponding to all 'supported CSS properties'
// of the following form:
//
//partial interface CSSStyleDeclaration {
//  [CEReactions] attribute [LegacyNullToEmptyString] CSSOMString _camel_cased_attribute;
//};
//partial interface CSSStyleDeclaration {
//  [CEReactions] attribute [LegacyNullToEmptyString] CSSOMString _webkit_cased_attribute;
//};
//partial interface CSSStyleDeclaration {
//  [CEReactions] attribute [LegacyNullToEmptyString] CSSOMString _dashed_attribute;
//};

// XXX TODO These should be added here.  Probably automatically generated
// from a list, with something like a `ReflectCSS` extended attribute to
// indicate their special treatment (they get forwarded to getPropertyValue
// or setProperty)

// XXX alternatively we could just handle this as a special case of
// our __get() / __set() helper, where we *don't* set
// @phan-forbid-undeclared-magic-properties and instead forward all
// unknown properties.  That would avoid generating a bunch of get* and
// set* methods.
