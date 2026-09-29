import{j as c}from"./app-EFKS6lVp.js";import{c as t}from"./button-DfvqZFMz.js";import{c as i}from"./createLucideIcon-D4Sf2R9V.js";import{a as s,C as a}from"./flash-messages-CH6HAC_q.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const l=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3",key:"1u773s"}],["path",{d:"M12 17h.01",key:"p32p05"}]],p=i("CircleHelp",l);function u({status:o,className:r}){const n={opted_in:{label:"Opted in",icon:a,cls:"border-brand bg-brand-soft text-[#2b4a08]"},opted_out:{label:"Opted out",icon:s,cls:"border-red-200 bg-red-50 text-red-700"},unknown:{label:"Unknown",icon:p,cls:"border-zinc-200 bg-zinc-50 text-zinc-600"}},e=n[o]??n.unknown;return c.jsxs("span",{className:t("inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium",e.cls,r),children:[c.jsx(e.icon,{className:"size-3"})," ",e.label]})}export{u as O};
