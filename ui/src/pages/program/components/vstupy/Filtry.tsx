import { FunctionComponent } from "preact";
import Select from "react-select";
import { GAMECON_KONSTANTY, ROKY } from "../../../../env";
import {
  useUrlState, useUrlStateMožnosti,
} from "../../../../store/program/selektory";
import { nastavFiltrLinií, nastavFiltrPřihlašovatelné, nastavFiltrRočník, nastavFiltrTagů } from "../../../../store/program/slices/urlSlice";

type TFiltryProps = {
  otevřeno: boolean;
};

const ROKY_OPTIONS = ROKY.concat(GAMECON_KONSTANTY.ROCNIK)
  .map((x) => ({ value: x, label: x }))
  .reverse();

const asValueLabel = <T,>(obj: T) => ({
  value: obj,
  label: obj,
});

// TODO: můj program je nefiltrovaný - zašednout všechny controly ve filtry a lehce i tlačítko filtry
export const Filtry: FunctionComponent<TFiltryProps> = (props) => {
  const { otevřeno } = props;

  const urlState = useUrlState();

  const urlStateMožnosti = useUrlStateMožnosti();

  // nastavFiltrLinie();

  return (
    <>
      <div
        class={
          "program_filtry_container clearfix" +
          (otevřeno ? " program_filtry_container_otevreno" : "")
        }
      >
        <div style={{ display: "flex", gap: 16 }}>
          <div style={{ width: "120px" }}>
            <Select
              value={asValueLabel(urlState.ročník)}
              onChange={e=>{nastavFiltrRočník(e?.value);}}
              options={ROKY_OPTIONS}
            />
          </div>
          <div style={{ flex: "1", maxWidth: "400px" }}>
            <Select
              placeholder="Linie"
              options={urlStateMožnosti.linie.map(asValueLabel)}
              closeMenuOnSelect={false}
              isMulti
              value={urlState.filtrLinie?.map(asValueLabel) ?? []}
              onChange={(e) => {
                nastavFiltrLinií(e.map((x) => x.value));
              }}
            />
          </div>
          <div style={{ flex: "1" }}>
            <Select
              placeholder="Tagy"
              options={urlStateMožnosti.tagy.map(asValueLabel)}
              isMulti
              closeMenuOnSelect={false}
              value={urlState.filtrTagy?.map(asValueLabel) ?? []}
              onChange={(e) => {
                nastavFiltrTagů(e.map((x) => x.value));
              }}
            />
          </div>
          <div style={{ minWidth: "300px" }} class="formular_polozka">
            <input style={{ marginTop: 0 }} placeholder="Hledej v textu" />
          </div>
        </div>

        <div>
          <button class="program_filtry_tlacitko">zvětšit</button>
          <button class="program_filtry_tlacitko">sdílej</button>
          <button class={"program_filtry_tlacitko" + (urlState.filtrPřihlašovatelné ? " aktivni" : "")}
          onClick={()=>{
            nastavFiltrPřihlašovatelné(!urlState.filtrPřihlašovatelné);
          }}
          >Přihlašovatelné</button>
          <button class="program_filtry_tlacitko">Detail</button>
        </div>
      </div>
    </>
  );
};

Filtry.displayName = "Filtry";
