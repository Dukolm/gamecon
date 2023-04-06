
import "./index.less";
import { renderRootComponent } from "@gamecon/core";
import { Obchod } from "./obchod";
import { initEnv } from "@gamecon/api";

console.log("Preact starting ...");

initEnv();
renderRootComponent("preact-obchod", Obchod);
