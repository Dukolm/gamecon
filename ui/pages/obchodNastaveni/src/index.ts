import { initEnv } from "@gamecon/api/src/env";

import "./index.less";
import { renderRootComponent } from "@gamecon/core";
import { ObchodNastaveni } from "./obchodNastaveni";

console.log("Preact starting ...");

initEnv();
renderRootComponent("preact-obchod-nastaveni", ObchodNastaveni);
