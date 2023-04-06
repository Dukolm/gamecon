import { initEnv } from "@gamecon/api/src/env";

import "@gamecon/core/src/base.less";
import "./index.less";
import { renderRootComponent } from "@gamecon/core";
import { ProgramWrapper } from "./testing/ProgramWrapper";
import { Program } from "./App";

// TODO: zbavit se html v db kde to jde (legenda text, program náhled ...)
// TODO: uklidit duplicitní less styly.
// TODO: github actions test na linter

//         Spouštění
//         Developement
//          jak pracovat se zustand
/*            ! Pro vytvoření nového slice:
                - má svou složku
                - má svůj vlastní klíč ve store
                  například: type ExmampleSlice = { example: {......} }
                  - může editovat pouze hodnoty ve svém klíčí
                  - může 
*/
console.log("Preact starting ...");

initEnv();
renderRootComponent("preact-program", Program, ProgramWrapper);
