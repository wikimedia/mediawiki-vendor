# Dodo 0.2.0 (2021-07-26)
* Update to IDLeDOM 0.7.2.
* Fix doctype creation and "significant whitespace" handling in DOMParser.
* Ensure that Document::documentElement is always populated.
* Ensure that Document::documentElement matches FilteredElementList where
  appropriate.
* EXPERIMENTAL: Added Node::getExtensionData() and Node::setExtensionData()
  methods to allow end-users to associate additional non-spec data off of
  Nodes.
* Implement DocumentFragment::querySelector(), ::querySelectorAll(), and
  ::getElementById().
* Implement Node::getNodePath() for PHP compatibility.
* Don't export .phan directory in composer package.

# Dodo 0.1.0 (2021-07-04)
Initial release.
