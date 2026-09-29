import{K as l,r as a,j as s}from"./app-EFKS6lVp.js";import{c as i}from"./button-DfvqZFMz.js";import{c as o}from"./createLucideIcon-D4Sf2R9V.js";import{X as p}from"./app-layout-D6VoTf62.js";function u(){const{flash:r}=l().props,[e,t]=a.useState(!0);return a.useEffect(()=>{t(!0)},[r==null?void 0:r.success,r==null?void 0:r.error,r==null?void 0:r.warning]),{flash:e?r:void 0,dismiss:()=>t(!1)}}/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const x=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]],b=o("CircleCheck",x);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]],y=o("CircleX",g);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const k=[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",key:"wmoenq"}],["path",{d:"M12 9v4",key:"juzpu7"}],["path",{d:"M12 17h.01",key:"p32p05"}]],h=o("TriangleAlert",k);function v({className:r}){const{flash:e,dismiss:t}=u();if(!(e!=null&&e.success)&&!(e!=null&&e.error)&&!(e!=null&&e.warning))return null;const n=[e.success&&{tone:"success",text:e.success,Icon:b},e.warning&&{tone:"warning",text:e.warning,Icon:h},e.error&&{tone:"error",text:e.error,Icon:y}].filter(Boolean);return s.jsx("div",{className:i("space-y-2",r),children:n.map(({tone:c,text:m,Icon:d})=>s.jsxs("div",{className:i("flex items-start gap-3 rounded-lg border px-4 py-3 text-sm",c==="success"&&"border-brand/60 bg-brand-soft text-[#2b4a08]",c==="warning"&&"border-amber-300 bg-amber-50 text-amber-900",c==="error"&&"border-red-300 bg-red-50 text-red-800"),children:[s.jsx(d,{className:"mt-0.5 size-4 shrink-0"}),s.jsx("p",{className:"flex-1 leading-relaxed break-words",children:m}),s.jsx("button",{type:"button",onClick:t,className:"opacity-60 hover:opacity-100","aria-label":"Dismiss",children:s.jsx(p,{className:"size-4"})})]},c))})}export{b as C,v as F,h as T,y as a};
