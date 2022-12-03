import { initEnv } from "./env";
import { renderPages } from "./pages"

// TODO: linter
// TODO: použít absolutní importy ?
// TODO: zvlášť ts a build konfigurace pro testování/admin/web
// TODO: pro api používat normalizovaný čas třeba unix timestamp
// TODO: program/muj_program při refreshi vrací nenalezeno. Preact by měl mít pod kontrolou komplet url za program/
// TODO: legendaText by NEMĚLO být html
// TODO: uklidit duplicitní less styly.
// TODO: revidovat názvosloví
// TODO: Vytvořit zálohy node_modules pro případ nekompatabilní změny balíčku a smazání staré verze
// TODO: github actions test na linter
// TODO: readme: návod na práci s ui BUILDING Vývoj atd. ...
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
console.log("Preact starting ...")

initEnv();
renderPages();
