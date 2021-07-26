// Extensions from https://w3c.github.io/DOM-Parsing/

// https://html.spec.whatwg.org/multipage/dynamic-markup-insertion.html#domparser
interface DOMParser {
  constructor();

  [NewObject] Document parseFromString(DOMString string, DOMParserSupportedType type);
};

enum DOMParserSupportedType {
  "text/html",
  "text/xml",
  "application/xml",
  "application/xhtml+xml",
  "image/svg+xml"
};

// https://w3c.github.io/DOM-Parsing/#the-xmlserializer-interface
[Exposed=Window]
interface XMLSerializer {
  constructor();
  DOMString serializeToString(Node root);
};

// https://w3c.github.io/DOM-Parsing/#the-innerhtml-mixin
interface mixin InnerHTML {
  [CEReactions] attribute [LegacyNullToEmptyString] DOMString innerHTML;
};

Element includes InnerHTML;
ShadowRoot includes InnerHTML;

// https://w3c.github.io/DOM-Parsing/#extensions-to-the-element-interface
partial interface Element {
  [CEReactions] attribute [LegacyNullToEmptyString] DOMString outerHTML;
  [CEReactions] undefined insertAdjacentHTML(DOMString position, DOMString text);
};

// https://w3c.github.io/DOM-Parsing/#extensions-to-the-range-interface
partial interface Range {
  [CEReactions, NewObject] DocumentFragment createContextualFragment(DOMString fragment);
};
