import { useProgramStore } from ".";
import { nastavStateZUrl, nastavUrlZState } from "./url";


// TODO: logiku pro autofetch na začátek první vlny (nějak vizuálně komunikovat že stránka byla načtena)

export const inicializujProgramStoreSubscribe = () =>{
  nastavStateZUrl();

  useProgramStore.subscribe(s=>s.urlState, ()=>{
    nastavUrlZState();
  });

  addEventListener("popstate", () => {
    nastavStateZUrl();
  });

  const načtiRok = useProgramStore.getState().načtiRok;

  const rok = useProgramStore.getState().urlState.rok;
  void načtiRok(rok);

  useProgramStore.subscribe(s=>s.urlState.rok, (rok)=>{
    void načtiRok(rok);
  });
};
