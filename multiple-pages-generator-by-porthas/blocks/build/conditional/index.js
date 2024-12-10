(()=>{var e={20:(e,t,o)=>{"use strict";var r=o(609),n=Symbol.for("react.element"),l=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),a=r.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};t.jsx=function(e,t,o){var r,s={},p=null,c=null;for(r in void 0!==o&&(p=""+o),void 0!==t.key&&(p=""+t.key),void 0!==t.ref&&(c=t.ref),t)l.call(t,r)&&!i.hasOwnProperty(r)&&(s[r]=t[r]);if(e&&e.defaultProps)for(r in t=e.defaultProps)void 0===s[r]&&(s[r]=t[r]);return{$$typeof:n,type:e,key:p,ref:c,props:s,_owner:a.current}}},848:(e,t,o)=>{"use strict";e.exports=o(20)},609:e=>{"use strict";e.exports=window.React},942:(e,t)=>{var o;!function(){"use strict";var r={}.hasOwnProperty;function n(){for(var e="",t=0;t<arguments.length;t++){var o=arguments[t];o&&(e=a(e,l(o)))}return e}function l(e){if("string"==typeof e||"number"==typeof e)return e;if("object"!=typeof e)return"";if(Array.isArray(e))return n.apply(null,e);if(e.toString!==Object.prototype.toString&&!e.toString.toString().includes("[native code]"))return e.toString();var t="";for(var o in e)r.call(e,o)&&e[o]&&(t=a(t,o));return t}function a(e,t){return t?e?e+" "+t:e+t:e}e.exports?(n.default=n,e.exports=n):void 0===(o=function(){return n}.apply(t,[]))||(e.exports=o)}()}},t={};function o(r){var n=t[r];if(void 0!==n)return n.exports;var l=t[r]={exports:{}};return e[r](l,l.exports,o),l.exports}o.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return o.d(t,{a:t}),t},o.d=(e,t)=>{for(var r in t)o.o(t,r)&&!o.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},o.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{"use strict";var e=o(609);const t=window.wp.compose,r=window.wp.blockEditor,n=window.wp.components,l=window.lodash;var a=o(942),i=o.n(a);const s=window.wp.element,p=window.wp.i18n,c=window.wp.primitives;var u=o(848);const m=(0,u.jsx)(c.SVG,{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",children:(0,u.jsx)(c.Path,{d:"M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"})}),d=(0,u.jsx)(c.SVG,{viewBox:"0 0 24 24",xmlns:"http://www.w3.org/2000/svg",children:(0,u.jsx)(c.Path,{d:"M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"})}),g=(0,u.jsx)(c.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,u.jsx)(c.Path,{d:"m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"})}),y=(0,u.jsx)(c.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24",children:(0,u.jsx)(c.Path,{d:"M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11Z"})}),h=({conditions:t,updateConditions:o,operators:r,CompareOperators:l,labels:a={},columnOptions:i})=>{const[c,u]=(0,s.useState)([]),h={applyFilters:(0,p.__)("How to Apply Filters","multiple-pages-generator-by-porthas"),matchAll:(0,p.__)("Show items that match all filters","multiple-pages-generator-by-porthas"),matchAny:(0,p.__)("Show items that match any filter","multiple-pages-generator-by-porthas"),emptyFilter:(0,p.__)("Empty filter","multiple-pages-generator-by-porthas"),collapse:(0,p.__)("Collapse","multiple-pages-generator-by-porthas"),expand:(0,p.__)("Expand","multiple-pages-generator-by-porthas"),removeFilter:(0,p.__)("Remove filter","multiple-pages-generator-by-porthas"),column:(0,p.__)("Column","multiple-pages-generator-by-porthas"),selectColumn:(0,p.__)("Select a column...","multiple-pages-generator-by-porthas"),operator:(0,p.__)("Operator","multiple-pages-generator-by-porthas"),value:(0,p.__)("Value","multiple-pages-generator-by-porthas"),addFilter:(0,p.__)("Add filter","multiple-pages-generator-by-porthas"),...a},w=(e,r,n)=>{const l=[...t.conditions];l[e][r]=n,o({...t,conditions:l})},_=e=>{if(!e.column)return h.emptyFilter;let t=`${e.column}`;return e.operator&&(t+=` ${b(e.operator).toLowerCase()}`,l.includes(e.operator)&&e.value&&(t+=` ${e.value}`)),{short:t.length>15?t.substring(0,12)+"...":t,full:t}},b=e=>{const t=r.find((t=>t.value===e));return t?t.label:e};return(0,e.createElement)(e.Fragment,null,(0,e.createElement)(n.SelectControl,{label:h.applyFilters,value:t.logic,options:[{label:h.matchAll,value:"all"},{label:h.matchAny,value:"any"}],onChange:e=>{o({...t,logic:e})}}),t.conditions.map(((a,s)=>(0,e.createElement)("div",{key:s,style:{marginBottom:"10px",padding:"0px 10px",border:"1px solid #ccc"}},(0,e.createElement)("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center"}},(0,e.createElement)("strong",{title:_(a).full},_(a).short),(0,e.createElement)("div",null,(0,e.createElement)(n.IconButton,{icon:c[s]?m:d,label:c[s]?h.collapse:h.expand,onClick:()=>(e=>{u((t=>{const o=[...t];return o[e]=!o[e],o}))})(s)}),(0,e.createElement)(n.IconButton,{icon:g,label:h.removeFilter,onClick:()=>(e=>{const r=[...t.conditions];r.splice(e,1),o({...t,conditions:r})})(s)}))),c[s]&&(0,e.createElement)(e.Fragment,null,i?(0,e.createElement)(n.SelectControl,{label:h.column,value:a.column,options:[{value:"",label:h.selectColumn},...i],onChange:e=>w(s,"column",e)}):(0,e.createElement)(n.TextControl,{label:h.column,value:a.column,onChange:e=>w(s,"column",e)}),(0,e.createElement)(n.SelectControl,{label:h.operator,value:a.operator,options:r,onChange:e=>w(s,"operator",e)}),l.includes(a.operator)&&(0,e.createElement)(n.TextControl,{label:h.value,value:a.value,onChange:e=>w(s,"value",e)}))))),t.conditions.length<10&&(0,e.createElement)(n.Button,{isSecondary:!0,onClick:()=>{if(t.conditions.length<10){const e=t.conditions.length;o({...t,conditions:[...t.conditions,{column:"",operator:r[0].value,value:""}]}),u((t=>{const o=[...t];return o[e]=!0,o}))}},icon:y},h.addFilter))},w=window.wp.hooks,_=window.mpgCondData.operators||[],b=(0,t.createHigherOrderComponent)((t=>o=>{const{attributes:l,setAttributes:a}=o,[i,c]=(0,s.useState)(l.mpgConditions||{conditions:[],logic:"all"}),u=e=>{c(e),a({mpgConditions:e})},m=Object.entries(_).map((([e,t])=>({label:t,value:e}))),d=Object.keys(window.mpgCondData.compareOperators)||[];return(0,e.createElement)(s.Fragment,null,(0,e.createElement)(t,{...o}),(0,e.createElement)(r.InspectorControls,null,(0,e.createElement)(n.PanelBody,{title:(0,p.__)("MPG Visibility Conditions","multiple-pages-generator-by-porthas")},(0,e.createElement)(h,{onChange:u,conditions:i,updateConditions:u,operators:m,CompareOperators:d,labels:{addFilter:(0,p.__)("Add Condition","multiple-pages-generator-by-porthas"),applyFilters:(0,p.__)("Show If","multiple-pages-generator-by-porthas"),matchAll:(0,p.__)("All conditions are met","multiple-pages-generator-by-porthas"),matchAny:(0,p.__)("Any condition is met","multiple-pages-generator-by-porthas"),removeFilter:(0,p.__)("Remove Condition","multiple-pages-generator-by-porthas"),filterTitle:(0,p.__)("Condition","multiple-pages-generator-by-porthas"),column:(0,p.__)("Column name","multiple-pages-generator-by-porthas"),operator:(0,p.__)("Condition","multiple-pages-generator-by-porthas"),value:(0,p.__)("Value","multiple-pages-generator-by-porthas")}}))))}),"withConditions"),v=(0,t.createHigherOrderComponent)((t=>o=>{const{attributes:r}=o,n=r.mpgConditions&&r.mpgConditions.conditions&&r.mpgConditions.conditions.length>0;return(0,e.createElement)(t,{...o,className:i()(o.className,{"mpg-has-condition":n}),wrapperProps:{...o.wrapperProps,"data-mpg-label":n?(0,p.__)("MPG Conditioned","multiple-pages-generator-by-porthas"):void 0}})}),"withConditionsIndicator");(0,w.addFilter)("editor.BlockEdit","mpg/conditions-inspector",b,3),(0,w.addFilter)("blocks.registerBlockType","mpg/conditions-register",(e=>(e.attributes=(0,l.assign)(e.attributes,{mpgConditions:{type:"object",default:{conditions:[],logic:"all"},properties:{conditions:{type:"array",items:{type:"object",properties:{column:{type:"string"},operator:{type:"string"},value:{type:"string"}}}},logic:{type:"string",enum:["all","any"]}}}}),e))),(0,w.addFilter)("editor.BlockListBlock","mpg/contextual-indicators",v)})()})();