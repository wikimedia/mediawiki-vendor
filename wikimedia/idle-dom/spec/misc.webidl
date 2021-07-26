// These definitions are from other standards, but are referenced from
// the DOM standard: EventHandler, DOMHighResTimeStamp, HTMLSlotElement
// In addition, HTMLSlotElement pulls in HTMLElement.

// https://html.spec.whatwg.org/multipage/webappapis.html#eventhandler
[LegacyTreatNonObjectAsNull]
callback EventHandlerNonNull = any (Event event);
typedef EventHandlerNonNull? EventHandler;

// https://w3c.github.io/hr-time/#dom-domhighrestimestamp
typedef double DOMHighResTimeStamp;

// https://html.spec.whatwg.org/multipage/scripting.html#htmlslotelement
[Exposed=Window,
 HTMLConstructor]
interface HTMLSlotElement : HTMLElement {
  [CEReactions, Reflect] attribute DOMString name;
  sequence<Node> assignedNodes(optional AssignedNodesOptions options = {});
  sequence<Element> assignedElements(optional AssignedNodesOptions options = {});
};

dictionary AssignedNodesOptions {
  boolean flatten = false;
};

interface mixin HTMLOrSVGElement {
  [SameObject] readonly attribute DOMStringMap dataset;
// TODO: Shouldn't be directly [Reflect]ed
  [Reflect] attribute DOMString nonce; // intentionally no [CEReactions]

//  [CEReactions] attribute boolean autofocus; // duplicated in HTML*Element
  [CEReactions] attribute long tabIndex;
//  undefined focus(optional FocusOptions options = {});
  undefined blur();
};

interface mixin HTMLHyperlinkElementUtils {
  [CEReactions] stringifier attribute USVString href;
  readonly attribute USVString origin;
  [CEReactions] attribute USVString protocol;
  [CEReactions] attribute USVString username;
  [CEReactions] attribute USVString password;
  [CEReactions] attribute USVString host;
  [CEReactions] attribute USVString hostname;
  [CEReactions] attribute USVString port;
  [CEReactions] attribute USVString pathname;
  [CEReactions] attribute USVString search;
  [CEReactions] attribute USVString hash;
};


[Exposed=Window,
 LegacyOverrideBuiltIns]
interface DOMStringMap {
  getter DOMString (DOMString name);
  [CEReactions] setter undefined (DOMString name, DOMString value);
  [CEReactions] deleter undefined (DOMString name);
};


[Exposed=Window]
interface TimeRanges {
  readonly attribute unsigned long length;
  double start(unsigned long index);
  double end(unsigned long index);
};


[Global=Window,
 Exposed=Window,
 LegacyUnenumerableNamedProperties]
interface Window : EventTarget {
  // the current browsing context
//  [LegacyUnforgeable] readonly attribute WindowProxy window;
//  [Replaceable] readonly attribute WindowProxy self;
  [LegacyUnforgeable] readonly attribute Document document;
  attribute DOMString name; 
  [PutForwards=href, LegacyUnforgeable] readonly attribute Location location;
//  readonly attribute History history;
//  readonly attribute CustomElementRegistry customElements;
//  [Replaceable] readonly attribute BarProp locationbar;
//  [Replaceable] readonly attribute BarProp menubar;
//  [Replaceable] readonly attribute BarProp personalbar;
//  [Replaceable] readonly attribute BarProp scrollbars;
//  [Replaceable] readonly attribute BarProp statusbar;
//  [Replaceable] readonly attribute BarProp toolbar;
  attribute DOMString status;
  undefined close();
  readonly attribute boolean closed;
  undefined stop();
  undefined focus();
  undefined blur();

  // other browsing contexts
//  [Replaceable] readonly attribute WindowProxy frames;
  [Replaceable] readonly attribute unsigned long length;
//  [LegacyUnforgeable] readonly attribute WindowProxy? top;
  attribute any opener;
//  [Replaceable] readonly attribute WindowProxy? parent;
  readonly attribute Element? frameElement;
//  WindowProxy? open(optional USVString url = "", optional DOMString target = "_blank", optional [LegacyNullToEmptyString] DOMString features = "");
//  getter object (DOMString name);
  // Since this is the global object, the IDL named getter adds a NamedPropertiesObject exotic
  // object on the prototype chain. Indeed, this does not make the global object an exotic object.
  // Indexed access is taken care of by the WindowProxy exotic object.

  // the user agent
  readonly attribute Navigator navigator; 
  readonly attribute boolean originAgentCluster;

  // user prompts
//  undefined alert();
//  undefined alert(DOMString message);
//  boolean confirm(optional DOMString message = "");
//  DOMString? prompt(optional DOMString message = "", optional DOMString default = "");
  undefined print();

  // undefined postMessage(any message, USVString targetOrigin, optional sequence<object> transfer = []);
  // undefined postMessage(any message, optional WindowPostMessageOptions options = {});

  // also has obsolete members
};
Window includes GlobalEventHandlers;
Window includes WindowEventHandlers;

// dictionary WindowPostMessageOptions : PostMessageOptions {
//  USVString targetOrigin = "/";
// };

partial interface Window {
  [Replaceable] readonly attribute (Event or undefined) event; // legacy
};

// https://html.spec.whatwg.org/multipage/media.html#audiotracklist
[Exposed=Window]
interface AudioTrackList : EventTarget {
  readonly attribute unsigned long length;
  getter AudioTrack (unsigned long index);
  [PHPExtension] getter AudioTrack? getTrackById(DOMString id);

  attribute EventHandler onchange;
  attribute EventHandler onaddtrack;
  attribute EventHandler onremovetrack;
};

[Exposed=Window]
interface AudioTrack {
  readonly attribute DOMString id;
  readonly attribute DOMString kind;
  readonly attribute DOMString label;
  readonly attribute DOMString language;
  attribute boolean enabled;
};

[Exposed=Window]
interface VideoTrackList : EventTarget {
  readonly attribute unsigned long length;
  getter VideoTrack (unsigned long index);
  [PHPExtension] getter VideoTrack? getTrackById(DOMString id);
  readonly attribute long selectedIndex;

  attribute EventHandler onchange;
  attribute EventHandler onaddtrack;
  attribute EventHandler onremovetrack;
};

[Exposed=Window]
interface VideoTrack {
  readonly attribute DOMString id;
  readonly attribute DOMString kind;
  readonly attribute DOMString label;
  readonly attribute DOMString language;
  attribute boolean selected;
};

// https://html.spec.whatwg.org/multipage/media.html#texttracklist
[Exposed=Window]
interface TextTrackList : EventTarget {
  readonly attribute unsigned long length;
  getter TextTrack (unsigned long index);
  [PHPExtension] getter TextTrack? getTrackById(DOMString id);

  attribute EventHandler onchange;
  attribute EventHandler onaddtrack;
  attribute EventHandler onremovetrack;
};


[Exposed=Window]
interface Navigator {
  // objects implementing this interface also implement the interfaces given below
};
Navigator includes NavigatorID;
Navigator includes NavigatorLanguage;
Navigator includes NavigatorOnLine;
//Navigator includes NavigatorContentUtils;
Navigator includes NavigatorCookies;
//Navigator includes NavigatorPlugins;
//Navigator includes NavigatorConcurrentHardware;


interface mixin NavigatorID {
  readonly attribute DOMString appCodeName; // constant "Mozilla"
  readonly attribute DOMString appName; // constant "Netscape"
  readonly attribute DOMString appVersion;
  readonly attribute DOMString platform;
  readonly attribute DOMString product; // constant "Gecko"
  [Exposed=Window] readonly attribute DOMString productSub;
  readonly attribute DOMString userAgent;
  [Exposed=Window] readonly attribute DOMString vendor;
  [Exposed=Window] readonly attribute DOMString vendorSub; // constant ""
};


partial interface mixin NavigatorID {
  [Exposed=Window] boolean taintEnabled(); // constant false
  [Exposed=Window] readonly attribute DOMString oscpu;
};


interface mixin NavigatorLanguage {
  readonly attribute DOMString language;
  //readonly attribute FrozenArray<DOMString> languages;
};


interface mixin NavigatorOnLine {
  readonly attribute boolean onLine;
};


interface mixin NavigatorCookies {
  readonly attribute boolean cookieEnabled;
};


[Exposed=Window]
interface ValidityState {
  readonly attribute boolean valueMissing;
  readonly attribute boolean typeMismatch;
  readonly attribute boolean patternMismatch;
  readonly attribute boolean tooLong;
  readonly attribute boolean tooShort;
  readonly attribute boolean rangeUnderflow;
  readonly attribute boolean rangeOverflow;
  readonly attribute boolean stepMismatch;
  readonly attribute boolean badInput;
  readonly attribute boolean customError;
  readonly attribute boolean valid;
};

interface mixin ElementContentEditable {
  [CEReactions] attribute DOMString contentEditable;
  [CEReactions, ReflectEnum=("enter","done","go","next","previous","search","send")] attribute DOMString enterKeyHint;
  readonly attribute boolean isContentEditable;
  [CEReactions, ReflectEnum=("none","text","tel","url","email","numeric","decimal","search")] attribute DOMString inputMode;
};

// https://heycam.github.io/webidl/#idl-DOMException
[Exposed=(Window,Worker), Serializable]
interface DOMException { // but see below note about ECMAScript binding
  constructor(optional DOMString message = "", optional DOMString name = "Error");
  readonly attribute DOMString name;
  // Use [PHPNoHint] to prevent the type from being emitted as a type hint,
  // since that prevents us from extending a 'real' PHP Exception type
  // (which doesn't have hints on these methods and don't allow overriding
  // them)
  readonly attribute [PHPNoHint] DOMString message;
  readonly attribute [PHPNoHint] unsigned short code;

  const unsigned short INDEX_SIZE_ERR = 1;
  const unsigned short DOMSTRING_SIZE_ERR = 2;
  const unsigned short HIERARCHY_REQUEST_ERR = 3;
  const unsigned short WRONG_DOCUMENT_ERR = 4;
  const unsigned short INVALID_CHARACTER_ERR = 5;
  const unsigned short NO_DATA_ALLOWED_ERR = 6;
  const unsigned short NO_MODIFICATION_ALLOWED_ERR = 7;
  const unsigned short NOT_FOUND_ERR = 8;
  const unsigned short NOT_SUPPORTED_ERR = 9;
  const unsigned short INUSE_ATTRIBUTE_ERR = 10;
  const unsigned short INVALID_STATE_ERR = 11;
  const unsigned short SYNTAX_ERR = 12;
  const unsigned short INVALID_MODIFICATION_ERR = 13;
  const unsigned short NAMESPACE_ERR = 14;
  const unsigned short INVALID_ACCESS_ERR = 15;
  const unsigned short VALIDATION_ERR = 16;
  const unsigned short TYPE_MISMATCH_ERR = 17;
  const unsigned short SECURITY_ERR = 18;
  const unsigned short NETWORK_ERR = 19;
  const unsigned short ABORT_ERR = 20;
  const unsigned short URL_MISMATCH_ERR = 21;
  const unsigned short QUOTA_EXCEEDED_ERR = 22;
  const unsigned short TIMEOUT_ERR = 23;
  const unsigned short INVALID_NODE_TYPE_ERR = 24;
  const unsigned short DATA_CLONE_ERR = 25;
};

// Interfaces for WebIDL "simple exceptions"
// https://heycam.github.io/webidl/#dfn-simple-exception
[PHPExtension]
interface SimpleException { };
[PHPExtension]
interface Error : SimpleException { };
[PHPExtension]
interface EvalError : SimpleException { };
[PHPExtension]
interface RangeError : SimpleException { };
[PHPExtension]
interface ReferenceError : SimpleException { };
[PHPExtension]
interface TypeError : SimpleException { };
[PHPExtension]
interface URIError : SimpleException { };
