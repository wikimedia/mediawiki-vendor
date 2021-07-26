// Extensions added for compatibility with PHP DOM* classes (xml extension)

partial interface Document {
  // This exists in PHP's built-in DOMDocument.  PHP specs say:
  // "Encoding of the document, as specified by the XML declaration.
  // This attribute is not present in the final DOM Level 3 specification,
  // but is the only way of manipulating XML document encoding in this
  // implementation."
  [PHPExtension] attribute DOMString encoding;

  // These are often used in PHP code instead of the "proper" DOMImplementation
  // or DOM parsing methods.
  [PHPExtension] (Document or boolean) load(DOMString source, optional unsigned long options = 0);
  [PHPExtension] (Document or boolean) loadXML(DOMString source, optional unsigned long options = 0);
  [PHPExtension] (DOMString or boolean) saveHTML(optional Node? node = null);
  [PHPExtension] (DOMString or boolean) saveXML(optional Node? node = null, optional unsigned long options = 0);
};

partial interface Element {
  // PHP requires you to tell it which attribute is the "id" for the Element
  // before it will populate the index used by Document::getElementById()
  // These can be no-ops in a standards-compliant DOM implementation
  [PHPExtension]
  undefined setIdAttribute(DOMString qualifiedName, boolean isId);
  [PHPExtension]
  undefined setIdAttributeNode(Attr attr, boolean isId);
  [PHPExtension]
  undefined setIdAttributeNS(DOMString namespace, DOMString qualifiedName, boolean isId);
};
