import { FunctionComponent } from "preact";
import Select from "react-select";
import { GAMECON_KONSTANTY, ROKY } from "../../../../env";
import {
  useUrlState,
  useUrlStateMožnostiLinie,
} from "../../../../store/program/selektory";
import { nastavFiltrLinie } from "../../../../store/program/slices/urlSlice";

type TFiltryProps = {
  otevřeno: boolean;
};

const ROKY_OPTIONS = ROKY.concat(GAMECON_KONSTANTY.ROCNIK)
  .map((x) => ({ value: x, label: x }))
  .reverse();

export const Filtry: FunctionComponent<TFiltryProps> = (props) => {
  const { otevřeno } = props;

  const řazení = useUrlStateMožnostiLinie();
  const urlState = useUrlState();

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
            <Select value={ROKY_OPTIONS[0]} options={ROKY_OPTIONS} />
          </div>
          <div style={{ flex: "1", maxWidth:"400px" }}>
            <Select
              options={řazení.map((x) => ({ value: x, label: x }))}
              isMulti={true}
              closeMenuOnSelect={false}
            />
          </div>
          <div style={{flex:"1"}}>
            <Select
              options={["tag 1", "tag 2"].map((x) => ({ value: x, label: x }))}
              isMulti={true}
              closeMenuOnSelect={false}
            />
          </div>
          <div style={{minWidth:"300px"}} class="formular_polozka">
            <input style={{marginTop: 0}} placeholder="Hledej v textu" />
          </div>
        </div>

        <div>
          <button class="program_filtry_tlacitko">zvětšit</button>
          <button class="program_filtry_tlacitko">sdílej</button>
          <button class="program_filtry_tlacitko">Zapisovatelné</button>
          <button class="program_filtry_tlacitko">Detail</button>
        </div>
      </div>
    </>
  );
};

Filtry.displayName = "Filtry";
